<?php

declare(strict_types=1);

namespace Application\Notifications\Outbox;

/**
 * Outbox message moved to DLQ state after retry exhaustion.
 */
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
