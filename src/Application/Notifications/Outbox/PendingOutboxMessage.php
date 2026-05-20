<?php

declare(strict_types=1);

namespace Application\Notifications\Outbox;

/**
 * Claimed outbox message ready for publication to the broker.
 */
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
