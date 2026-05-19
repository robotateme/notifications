<?php

namespace Application\Notifications\Inbox;

final readonly class IncomingMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $topic,
        public ?string $key,
        public array $payload,
    ) {}
}
