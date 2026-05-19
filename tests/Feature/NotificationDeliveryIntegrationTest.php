<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use App\Models\OutboxMessage;
use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Application\Notifications\Ports\MessageBroker;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Domain\Notifications\NotificationStatus;
use Domain\Shared\Timestamp;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Notifications\Events\OutboxMessageStatus;
use Infrastructure\Notifications\Identity\UuidNotificationIdGenerator;
use Mockery;
use Tests\TestCase;

final class NotificationDeliveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_bulk_notification_flows_from_api_through_queue_to_provider_and_database(): void
    {
        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        $response = $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => NotificationPriority::Marketing->value,
            'message' => 'Service window starts tonight.',
            'recipients' => ['subscriber@example.com'],
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.0.status', NotificationStatus::Queued->value);

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'notification.queued')->count());

        $this->artisan('queue:work', [
            '--queue' => 'notifications',
            '--once' => true,
            '--tries' => 1,
        ])->assertExitCode(0);

        $message = NotificationMessage::query()->firstOrFail();

        $this->assertSame(NotificationStatus::Sent->value, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->processing_at);
        $this->assertNotNull($message->sent_at);
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'notification.sent')->count());
    }

    public function test_outbox_publish_process_is_idempotent(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => NotificationPriority::Marketing->value,
            'message' => 'Service window starts tonight.',
            'recipients' => ['subscriber@example.com'],
        ])->assertAccepted();

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldReceive('publish')->once();
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 1 outbox message(s).')
            ->assertExitCode(0);

        $this->assertSame(OutboxMessageStatus::Published->value, OutboxMessage::query()->firstOrFail()->status);

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldNotReceive('publish');
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 0 outbox message(s).')
            ->assertExitCode(0);
    }

    public function test_outbox_message_is_moved_to_dead_status_after_retry_limit(): void
    {
        $this->postJson('/api/notifications/bulk', [
            'channel' => 'email',
            'priority' => NotificationPriority::Marketing->value,
            'message' => 'Service window starts tonight.',
            'recipients' => ['subscriber@example.com'],
        ])->assertAccepted();

        $outbox = OutboxMessage::query()->firstOrFail();
        $outbox->forceFill([
            'attempts' => 4,
            'available_at' => Timestamp::now(),
        ])->save();

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldReceive('publish')->once()->andThrow(new Exception('Kafka is unavailable.'));
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 0 outbox message(s).')
            ->assertExitCode(0);

        $outbox->refresh();

        $this->assertSame(OutboxMessageStatus::Dead->value, $outbox->status);
        $this->assertSame(5, $outbox->attempts);
        $this->assertNull($outbox->available_at);
        $this->assertSame('Kafka is unavailable.', $outbox->last_error);

        $broker = Mockery::mock(MessageBroker::class);
        $broker->shouldNotReceive('publish');
        $this->app->instance(MessageBroker::class, $broker);

        $this->artisan('outbox:publish', ['--limit' => 100])
            ->expectsOutput('Published 0 outbox message(s).')
            ->assertExitCode(0);
    }

    public function test_dead_outbox_messages_can_be_listed_and_retried(): void
    {
        $message = OutboxMessage::query()->create([
            'event_id' => 'event-1',
            'topic' => 'notifications.events',
            'event_name' => 'notification.queued',
            'aggregate_id' => 'notification-1',
            'payload' => ['event_id' => 'event-1'],
            'status' => OutboxMessageStatus::Dead->value,
            'attempts' => 5,
            'last_error' => 'Kafka is unavailable.',
        ]);

        $this->artisan('outbox:dead', ['--limit' => 10])
            ->expectsTable(
                ['ID', 'Event ID', 'Topic', 'Event', 'Aggregate ID', 'Attempts', 'Last Error'],
                [[
                    $message->id,
                    'event-1',
                    'notifications.events',
                    'notification.queued',
                    'notification-1',
                    5,
                    'Kafka is unavailable.',
                ]],
            )
            ->assertExitCode(0);

        $this->artisan('outbox:retry-dead', ['id' => $message->id])
            ->expectsOutput("Dead outbox message {$message->id} was returned to pending status.")
            ->assertExitCode(0);

        $message->refresh();

        $this->assertSame(OutboxMessageStatus::Pending->value, $message->status);
        $this->assertNotNull($message->available_at);
        $this->assertNull($message->last_error);

        $this->artisan('outbox:dead', ['--limit' => 10])
            ->expectsOutput('No dead outbox messages.')
            ->assertExitCode(0);
    }

    public function test_retry_dead_outbox_message_reports_missing_message(): void
    {
        $this->artisan('outbox:retry-dead', ['id' => 404])
            ->expectsOutput('Dead outbox message 404 was not found.')
            ->assertExitCode(1);
    }

    public function test_duplicate_job_does_not_call_provider_after_message_was_sent(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'subscriber@example.com',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'subscriber@example.com',
            'body' => 'Hello.',
            'status' => NotificationStatus::Sent->value,
            'queued_at' => Timestamp::now(),
            'sent_at' => Timestamp::now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldNotReceive('send');
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->uuid))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $this->assertSame(NotificationStatus::Sent->value, $message->refresh()->status);
        $this->assertSame(0, $message->attempts);
    }

    public function test_failed_attempt_is_retryable_and_next_attempt_can_send_message(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'subscriber@example.com',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'subscriber@example.com',
            'body' => 'Hello.',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once()->andThrow(new Exception('Gateway timeout.'));
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        try {
            (new SendNotificationJob($message->uuid))->handle($this->app->make(SendQueuedNotificationHandler::class));
            $this->fail('Expected temporary gateway exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Gateway timeout.', $exception->getMessage());
        }

        $message->refresh();

        $this->assertSame(NotificationStatus::Queued->value, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('Gateway timeout.', $message->last_error);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->uuid))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $message->refresh();

        $this->assertSame(NotificationStatus::Sent->value, $message->status);
        $this->assertSame(2, $message->attempts);
        $this->assertNull($message->last_error);
    }

    private static function notificationId(): string
    {
        return (new UuidNotificationIdGenerator())->generate();
    }
}
