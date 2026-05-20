<?php

declare(strict_types=1);

namespace Application\Notifications\Inbox;

/**
 * Message envelope received by a Kafka consumer before business handling.
 *
 * `eventId` and `consumerName` form the idempotency boundary in the inbox table.
 */
final readonly class IncomingMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $consumerName,
        public string $topic,
        public ?string $key,
        public array $payload,
    ) {}
}
