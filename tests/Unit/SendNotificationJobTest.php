<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Domain\Notifications\NotificationStatus;
use Domain\Shared\Timestamp;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Notifications\Identity\UuidNotificationIdGenerator;
use Mockery;
use Tests\TestCase;

final class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_notification_as_sent(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'customer@example.com',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->uuid))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $message->refresh();

        $this->assertSame(NotificationStatus::Sent->value, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->processing_at);
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->last_error);
    }

    public function test_job_records_failure_and_rethrows_delivery_errors(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'invalid',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'invalid',
            'body' => 'Hello.',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery
            ->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Delivery provider rejected the message.'));
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        try {
            (new SendNotificationJob($message->uuid))->handle($this->app->make(SendQueuedNotificationHandler::class));
            $this->fail('Expected delivery exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Delivery provider rejected the message.', $exception->getMessage());
        }

        $message->refresh();

        $this->assertSame(NotificationStatus::Queued->value, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNull($message->dropped_at);
        $this->assertSame('Delivery provider rejected the message.', $message->last_error);
    }

    public function test_job_marks_notification_as_dropped_on_last_retry_attempt(): void
    {
        $message = NotificationMessage::query()->create([
            'uuid' => self::notificationId(),
            'subscriber_id' => 'invalid',
            'channel' => NotificationChannel::Email->value,
            'priority' => NotificationPriority::Marketing->value,
            'recipient' => 'invalid',
            'body' => 'Hello.',
            'status' => NotificationStatus::Queued->value,
            'queued_at' => Timestamp::now(),
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery
            ->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Retries exhausted.'));
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        $job = (new SendNotificationJob($message->uuid))->withFakeQueueInteractions();
        $job->job->attempts = 3;

        try {
            $job->handle($this->app->make(SendQueuedNotificationHandler::class));
            $this->fail('Expected delivery exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Retries exhausted.', $exception->getMessage());
        }

        $message->refresh();

        $this->assertSame(NotificationStatus::Dropped->value, $message->status);
        $this->assertNotNull($message->dropped_at);
        $this->assertSame('Retries exhausted.', $message->last_error);
    }

    private static function notificationId(): string
    {
        return (new UuidNotificationIdGenerator())->generate();
    }
}
