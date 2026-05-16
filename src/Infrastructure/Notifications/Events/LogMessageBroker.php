<?php

namespace Infrastructure\Notifications\Events;

use Application\Notifications\Ports\MessageBroker;
use Illuminate\Support\Facades\Log;

class LogMessageBroker implements MessageBroker
{
    public function publish(string $topic, string $key, array $payload): void
    {
        Log::info('Outbox message published to broker.', [
            'topic' => $topic,
            'key' => $key,
            'payload' => $payload,
        ]);
    }
}
