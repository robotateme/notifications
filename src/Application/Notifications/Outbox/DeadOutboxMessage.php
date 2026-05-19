<?php

namespace Application\Notifications\Outbox;

final readonly class DeadOutboxMessage
{
    public function __construct(
        public int $id,
        public string $eventId,
        public string $topic,
        public string $eventName,
        public string $aggregateId,
        public int $attempts,
        public ?string $lastError,
    ) {}
}
