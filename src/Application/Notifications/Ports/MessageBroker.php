<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

interface MessageBroker
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $topic, string $key, array $payload): void;
}
