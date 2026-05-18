<?php

namespace Application\Notifications\Outbox;

final readonly class PendingOutboxMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $id,
        public string $topic,
        public string $aggregateId,
        public array $payload,
    ) {}
}
