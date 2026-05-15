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
            ->assertJsonPath('data.channel', 'email')
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
}
