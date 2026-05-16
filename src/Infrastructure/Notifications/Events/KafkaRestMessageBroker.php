<?php

namespace Infrastructure\Notifications\Events;

use Application\Notifications\Ports\MessageBroker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class KafkaRestMessageBroker implements MessageBroker
{
    /**
     * @throws RequestException
     */
    public function publish(string $topic, string $key, array $payload): void
    {
        Http::accept('application/vnd.kafka.v2+json')
            ->contentType('application/vnd.kafka.json.v2+json')
            ->post(rtrim(config('kafka.rest_url'), '/').'/topics/'.$topic, [
                'records' => [
                    [
                        'key' => $key,
                        'value' => $payload,
                    ],
                ],
            ])
            ->throw();
    }
}
