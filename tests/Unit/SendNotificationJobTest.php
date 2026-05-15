<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationMessage;
use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_notification_as_sent(): void
    {
        $message = NotificationMessage::query()->create([
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'customer@example.com',
            'body' => 'Hello.',
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery->shouldReceive('send')->once();
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        (new SendNotificationJob($message->id))->handle($this->app->make(SendQueuedNotificationHandler::class));

        $message->refresh();

        $this->assertSame(NotificationMessage::STATUS_SENT, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->processing_at);
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->last_error);
    }

    public function test_job_marks_notification_as_failed_and_rethrows_delivery_errors(): void
    {
        $message = NotificationMessage::query()->create([
            'channel' => NotificationMessage::CHANNEL_EMAIL,
            'recipient' => 'invalid',
            'body' => 'Hello.',
        ]);

        $delivery = Mockery::mock(NotificationDeliveryGateway::class);
        $delivery
            ->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Delivery provider rejected the message.'));
        $this->app->instance(NotificationDeliveryGateway::class, $delivery);

        try {
            (new SendNotificationJob($message->id))->handle($this->app->make(SendQueuedNotificationHandler::class));
            $this->fail('Expected delivery exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Delivery provider rejected the message.', $exception->getMessage());
        }

        $message->refresh();

        $this->assertSame(NotificationMessage::STATUS_FAILED, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->failed_at);
        $this->assertSame('Delivery provider rejected the message.', $message->last_error);
    }
}
