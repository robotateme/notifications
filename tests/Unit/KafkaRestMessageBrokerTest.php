<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Infrastructure\Notifications\Events\KafkaRestMessageBroker;
use Tests\TestCase;

class KafkaRestMessageBrokerTest extends TestCase
{
    public function test_broker_publishes_message_to_kafka_rest_proxy(): void
    {
        config()->set('kafka.rest_url', 'http://kafka-rest:8082');

        Http::fake([
            'kafka-rest:8082/topics/notifications.events' => Http::response([], 200),
        ]);

        (new KafkaRestMessageBroker)->publish('notifications.events', 'notification-1', [
            'event_name' => 'notification.sent',
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://kafka-rest:8082/topics/notifications.events'
            && $request['records'][0]['key'] === 'notification-1'
            && $request['records'][0]['value']['event_name'] === 'notification.sent');
    }
}
