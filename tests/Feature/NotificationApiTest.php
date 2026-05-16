<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationApiTest extends TestCase
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
            ->assertJsonPath('data.idempotency_key', 'order-1001-email')
            ->assertJsonPath('data.subscriber_id', 'customer@example.com')
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.priority', NotificationMessage::PRIORITY_MARKETING)
            ->assertJsonPath('data.status', NotificationMessage::STATUS_QUEUED);

        $message = NotificationMessage::query()->firstOrFail();

        Queue::assertPushed(
            SendNotificationJob::class,
            fn (SendNotificationJob $job): bool => $job->notificationMessageId === $message->id
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
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_notification_can_be_fetched_by_public_id(): void
    {
        $message = NotificationMessage::query()->create([
            'channel' => NotificationMessage::CHANNEL_SMS,
            'recipient' => '+15555550100',
            'body' => 'Code: 123456',
        ]);

        $this->getJson("/api/notifications/{$message->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $message->uuid)
            ->assertJsonPath('data.channel', NotificationMessage::CHANNEL_SMS);
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
            ->assertJsonPath('data.0.status', NotificationMessage::STATUS_QUEUED)
            ->assertJsonPath('data.0.priority', NotificationMessage::PRIORITY_MARKETING);

        $this->assertSame(2, NotificationMessage::query()->count());
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
            ->assertJsonPath('data.0.status', NotificationMessage::STATUS_QUEUED)
            ->assertJsonPath('data.0.priority', NotificationMessage::PRIORITY_TRANSACTIONAL);

        $this->assertSame(2, NotificationMessage::query()->where('status', NotificationMessage::STATUS_QUEUED)->count());
        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_delivery_status_can_be_confirmed(): void
    {
        $message = NotificationMessage::query()->create([
            'subscriber_id' => 'customer-1',
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
            'status' => NotificationMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->postJson("/api/notifications/{$message->uuid}/delivery-status", [
            'status' => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', NotificationMessage::STATUS_DELIVERED);

        $this->assertSame(NotificationMessage::STATUS_DELIVERED, $message->refresh()->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_subscriber_notification_history_is_returned(): void
    {
        NotificationMessage::query()->create([
            'subscriber_id' => 'subscriber-1',
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'first@example.com',
            'body' => 'First.',
            'status' => NotificationMessage::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        NotificationMessage::query()->create([
            'subscriber_id' => 'subscriber-1',
            'channel' => NotificationMessage::CHANNEL_SMS,
            'recipient' => '+15555550100',
            'body' => 'Second.',
            'status' => NotificationMessage::STATUS_DROPPED,
            'dropped_at' => now(),
            'last_error' => 'Invalid phone number.',
        ]);

        NotificationMessage::query()->create([
            'subscriber_id' => 'subscriber-2',
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'other@example.com',
            'body' => 'Other.',
        ]);

        $this->getJson('/api/subscribers/subscriber-1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subscriber_id', 'subscriber-1')
            ->assertJsonPath('data.1.subscriber_id', 'subscriber-1');
    }
}
