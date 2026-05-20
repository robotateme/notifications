<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

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
