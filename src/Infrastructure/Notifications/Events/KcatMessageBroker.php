<?php

namespace Infrastructure\Notifications\Events;

use Application\Notifications\Ports\MessageBroker;
use RuntimeException;
use Symfony\Component\Process\Process;

class KcatMessageBroker implements MessageBroker
{
    public function publish(string $topic, string $key, array $payload): void
    {
        $process = new Process([
            'kcat',
            '-P',
            '-b',
            config('kafka.brokers'),
            '-t',
            $topic,
            '-K',
            ':',
        ]);

        $process->setInput($key.':'.json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Kafka publish failed.');
        }
    }
}
