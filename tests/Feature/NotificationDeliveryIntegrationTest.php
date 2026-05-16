<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use App\Models\OutboxMessage;
use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Application\Notifications\Ports\MessageBroker;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_bulk_notification_flows_from_api_through_queue_to_provider_and_database(): void
    {
        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Service window starts tonight.',
            'recipients' => ['subscriber@example.com'],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.0.status', NotificationMessage::STATUS_QUEUED);

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'notification.queued')->count());

        $this->artisan('queue:work', [
            '--queue' => 'notifications',
            '--once' => true,
            '--tries' => 1,
        ])->assertExitCode(0);

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertSame(NotificationMessage::STATUS_SENT, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->processing_at);
        $this->assertNotNull($message->sent_at);
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'notification.sent')->count());
    }

    public function test_outbox_publish_process_is_idempotent(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Service window starts tonight.',
            'recipients' => ['subscriber@example.com'],
        ])->assertAccepted();

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldReceive('publish')->once();
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 1 outbox message(s).')
            ->assertExitCode(0);

        $this->assertSame(OutboxMessage::STATUS_PUBLISHED, OutboxMessage::query()->firstOrFail()->status);

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldNotReceive('publish');
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 0 outbox message(s).')
            ->assertExitCode(0);
    }

    public function test_duplicate_job_does_not_call_provider_after_message_was_sent(): void
    {
        $message = NotificationMessage::query()->create([
            'subscriber_id' => 'subscriber@example.com',
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'subscriber@example.com',
            'body' => 'Hello.',
            'status' => NotificationMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldNotReceive('send');
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->id))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $this->assertSame(NotificationMessage::STATUS_SENT, $message->refresh()->status);
        $this->assertSame(0, $message->attempts);
    }

    public function test_failed_attempt_is_retryable_and_next_attempt_can_send_message(): void
    {
        $message = NotificationMessage::query()->create([
            'subscriber_id' => 'subscriber@example.com',
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'subscriber@example.com',
            'body' => 'Hello.',
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once()->andThrow(new Exception('Gateway timeout.'));
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        try {
            (new SendNotificationJob($message->id))->handle($this->app->make(SendQueuedNotificationHandler::class));
            $this->fail('Expected temporary gateway exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Gateway timeout.', $exception->getMessage());
        }

        $message->refresh();

        $this->assertSame(NotificationMessage::STATUS_QUEUED, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('Gateway timeout.', $message->last_error);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->id))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $message->refresh();

        $this->assertSame(NotificationMessage::STATUS_SENT, $message->status);
        $this->assertSame(2, $message->attempts);
        $this->assertNull($message->last_error);
    }
}
