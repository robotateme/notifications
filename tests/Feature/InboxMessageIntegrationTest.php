<?php

declare(strict_types=1);

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
            consumerName: 'delivery-status-consumer',
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
        $this->assertSame('delivery-status-consumer', $inbox->consumer_name);
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
            consumerName: 'delivery-status-consumer',
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

    public function test_processing_incoming_message_is_not_processed_twice(): void
    {
        $handler = $this->app->make(ProcessInboxMessageHandler::class);
        $message = new IncomingMessage(
            eventId: 'provider-delivery-event-3',
            consumerName: 'delivery-status-consumer',
            topic: 'provider.delivery-status',
            key: 'notification-3',
            payload: ['event_id' => 'provider-delivery-event-3'],
        );
        $calls = 0;

        InboxMessage::query()->create([
            'event_id' => 'provider-delivery-event-3',
            'consumer_name' => 'delivery-status-consumer',
            'topic' => 'provider.delivery-status',
            'message_key' => 'notification-3',
            'payload' => ['event_id' => 'provider-delivery-event-3'],
            'status' => InboxMessageStatus::Processing->value,
        ]);

        $processed = $handler->handle($message, function () use (&$calls): void {
            $calls++;
        });

        $this->assertFalse($processed);
        $this->assertSame(0, $calls);
    }

    public function test_same_event_can_be_processed_by_different_consumers(): void
    {
        $handler = $this->app->make(ProcessInboxMessageHandler::class);
        $calls = 0;

        $first = $handler->handle(new IncomingMessage(
            eventId: 'shared-provider-event',
            consumerName: 'delivery-status-consumer',
            topic: 'provider.delivery-status',
            key: 'notification-4',
            payload: ['event_id' => 'shared-provider-event'],
        ), function () use (&$calls): void {
            $calls++;
        });

        $second = $handler->handle(new IncomingMessage(
            eventId: 'shared-provider-event',
            consumerName: 'audit-consumer',
            topic: 'provider.delivery-status',
            key: 'notification-4',
            payload: ['event_id' => 'shared-provider-event'],
        ), function () use (&$calls): void {
            $calls++;
        });

        $this->assertTrue($first);
        $this->assertTrue($second);
        $this->assertSame(2, $calls);
        $this->assertSame(2, InboxMessage::query()->count());
    }
}
