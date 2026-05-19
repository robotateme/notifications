<?php

namespace Tests\Feature;

use App\Models\InboxMessage;
use Application\Notifications\Commands\ProcessInboxMessageHandler;
use Application\Notifications\Inbox\IncomingMessage;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Notifications\Events\InboxMessageStatus;
use Tests\TestCase;

final class InboxMessageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_message_is_processed_once(): void
    {
        $handler = $this->app->make(ProcessInboxMessageHandler::class);
        $message = new IncomingMessage(
            eventId: 'provider-delivery-event-1',
            topic: 'provider.delivery-status',
            key: 'notification-1',
            payload: [
                'event_id' => 'provider-delivery-event-1',
                'notification_id' => 'notification-1',
                'status' => 'delivered',
            ],
        );
        $calls = 0;

        $first = $handler->handle($message, function () use (&$calls): void {
            $calls++;
        });

        $second = $handler->handle($message, function () use (&$calls): void {
            $calls++;
        });

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(1, $calls);
        $this->assertSame(1, InboxMessage::query()->count());

        $inbox = InboxMessage::query()->firstOrFail();

        $this->assertSame(InboxMessageStatus::Processed->value, $inbox->status);
        $this->assertSame('provider-delivery-event-1', $inbox->event_id);
        $this->assertSame('provider.delivery-status', $inbox->topic);
        $this->assertSame('notification-1', $inbox->message_key);
        $this->assertNotNull($inbox->processed_at);
        $this->assertNull($inbox->last_error);
    }

    public function test_failed_incoming_message_can_be_reprocessed(): void
    {
        $handler = $this->app->make(ProcessInboxMessageHandler::class);
        $message = new IncomingMessage(
            eventId: 'provider-delivery-event-2',
            topic: 'provider.delivery-status',
            key: 'notification-2',
            payload: ['event_id' => 'provider-delivery-event-2'],
        );

        try {
            $handler->handle($message, fn () => throw new Exception('Temporary consumer error.'));
            $this->fail('Expected consumer exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('Temporary consumer error.', $exception->getMessage());
        }

        $inbox = InboxMessage::query()->firstOrFail();

        $this->assertSame(InboxMessageStatus::Failed->value, $inbox->status);
        $this->assertSame('Temporary consumer error.', $inbox->last_error);

        $processed = $handler->handle($message, fn () => null);

        $this->assertTrue($processed);
        $this->assertSame(InboxMessageStatus::Processed->value, $inbox->refresh()->status);
        $this->assertNull($inbox->last_error);
    }
}
