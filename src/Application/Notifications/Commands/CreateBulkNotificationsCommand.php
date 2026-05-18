<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

final readonly class CreateBulkNotificationsCommand
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $body,
        public array $recipients,
        public ?string $subject = null,
        public ?string $idempotencyKey = null,
    ) {}
}
