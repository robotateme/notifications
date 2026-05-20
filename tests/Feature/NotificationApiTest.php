<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use App\Models\OutboxMessage;
use Application\Notifications\Idempotency\IdempotencyKeyFingerprint;
use Application\Notifications\Ports\DomainEventPublisher;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Domain\Notifications\NotificationStatus;
use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Infrastructure\Notifications\Identity\UuidNotificationIdGenerator;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_can_be_queued(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/notifications', [
            'idempotency_key' => 'order-1001-email',
            'channel' => 'email',
            'recipient' => 'customer@example.com',
            'subject' => 'Order shipped',
            'body' => 'Your order is on the way.',
            'payload' => ['order_id' => 1001],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.idempotency_key_fingerprint', hash('sha256', 'order-1001-email'))
            ->assertJsonPath('data.subscriber_id', 'customer@example.com')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.priority', NotificationPriority::Marketing->value)
            ->assertJsonPath('data.status', NotificationStatus::Queued->value);

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertSame(hash('sha256', 'order-1001-email'), $message->idempotency_key);

        Queue::assertPushed(
            SendNotificationJob::class,
            fn (SendNotificationJob $job): bool => $job->notificationId === $message->uuid
        );
    }

    public function test_idempotency_key_returns_existing_notification_without_requeueing(): void
    {
        Queue::fake();

        $payload = [
            'idempotency_key' => 'same-request',
            'channel' => 'email',
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
        ];

        $first = $this->postJson('/api/notifications', $payload);
        $second = $this->postJson('/api/notifications', $payload);

        $first->assertAccepted();
        $second
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, NotificationMessage::query()->count());
        $this->assertSame(hash('sha256', 'same-request'), NotificationMessage::query()->firstOrFail()->idempotency_key);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_idempotency_key_length_is_limited(): void
    {
        Queue::fake();

        $this->postJson('/api/notifications', [
            'idempotency_key' => str_repeat('a', IdempotencyKeyFingerprint::MAX_EXTERNAL_LENGTH + 1),
            'channel' => 'email',
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->assertSame(0, NotificationMessage::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_notification_can_be_fetched_by_public_id(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => '+15555550100',
            'channel' => NotificationChannel::Sms->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => '+15555550100',
            'body' => 'Code: 123456',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $this->getJson("/api/notifications/{$message->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $message->uuid)
            ->assertJsonPath('data.channel', NotificationChannel::Sms->value);
    }

    public function test_notification_request_is_validated(): void
    {
        Queue::fake();

        $this->postJson('/api/notifications', [
            'channel' => 'fax',
            'recipient' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['channel', 'recipient', 'body']);

        Queue::assertNothingPushed();
    }

    public function test_notification_creation_is_rolled_back_when_outbox_write_fails(): void
    {
        Queue::fake();

        $this->app->instance(DomainEventPublisher::class, new class implements DomainEventPublisher
        {
            public function publish(DomainEvent $event): void
            {
                throw new Exception('Outbox write failed.');
            }
        });

        $response = $this->postJson('/api/notifications', [
            'channel' => 'email',
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
        ]);

        $response->assertStatus(500);

        $this->assertSame(0, NotificationMessage::query()->count());
        $this->assertSame(0, OutboxMessage::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_bulk_marketing_notifications_are_queued(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/notifications/bulk', [
            'idempotency_key' => 'campaign-42',
            'channel' => 'email',
            'priority' => 'marketing',
            'body' => 'Sale starts today.',
            'recipients' => [
                'first@example.com',
                'second@example.com',
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', NotificationStatus::Queued->value)
            ->assertJsonPath('data.0.priority', NotificationPriority::Marketing->value);

        $this->assertSame(2, NotificationMessage::query()->count());
        $this->assertSame(
            [
                hash('sha256', 'campaign-42:0:first@example.com'),
                hash('sha256', 'campaign-42:1:second@example.com'),
            ],
            NotificationMessage::query()->orderBy('id')->pluck('idempotency_key')->all(),
        );
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_transactional_bulk_notifications_are_queued_to_high_priority_queue(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'sms',
            'priority' => 'transactional',
            'body' => 'Your access code is 123456.',
            'recipients' => [
                '+15555550100',
                '+15555550101',
            ],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', NotificationStatus::Queued->value)
            ->assertJsonPath('data.0.priority', NotificationPriority::Transactional->value);

        $this->assertSame(2, NotificationMessage::query()->where('status', NotificationStatus::Queued->value)->count());
        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_delivery_status_can_be_confirmed(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'customer-1',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
            'status' => NotificationStatus::Sent->value,
            'queued_at' => Timestamp::now(),
            'sent_at' => Timestamp::now(),
        ]);

        $this->postJson("/api/notifications/{$message->uuid}/delivery-status", [
            'status' => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', NotificationStatus::Delivered->value);

        $this->assertSame(NotificationStatus::Delivered->value, $message->refresh()->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_subscriber_notification_history_is_returned(): void
    {
        NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'subscriber-1',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'first@example.com',
            'body' => 'First.',
            'status' => NotificationStatus::Delivered->value,
            'queued_at' => Timestamp::now(),
            'delivered_at' => Timestamp::now(),
        ]);

        NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'subscriber-1',
            'channel' => NotificationChannel::Sms->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => '+15555550100',
            'body' => 'Second.',
            'status' => NotificationStatus::Dropped->value,
            'queued_at' => Timestamp::now(),
            'dropped_at' => Timestamp::now(),
            'last_error' => 'Invalid phone number.',
        ]);

        NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'subscriber-2',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'other@example.com',
            'body' => 'Other.',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $this->getJson('/api/subscribers/subscriber-1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subscriber_id', 'subscriber-1')
            ->assertJsonPath('data.1.subscriber_id', 'subscriber-1');
    }

    private static function notificationId(): string
    {
        return (new UuidNotificationIdGenerator)->generate();
    }
}
