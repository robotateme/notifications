<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

/**
 * Command for queuing one notification.
 *
 * `idempotencyKey` is the external client key before hashing.
 * `traceId` is the correlation id propagated to jobs, outbox and Kafka.
 *
 * @param  array<string, mixed>|null  $payload
 */
final readonly class CreateNotificationCommand
{
    public function __construct(
        public ?string $idempotencyKey,
        public ?string $traceId,
        public string $subscriberId,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $recipient,
        public ?string $subject,
        public ?string $body,
        public ?array $payload,
    ) {}
}
