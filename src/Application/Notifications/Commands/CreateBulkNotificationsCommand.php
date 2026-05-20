<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

/**
 * Command for queuing the same SMS/Email message for many recipients.
 *
 * `idempotencyKey` is expanded per recipient and then hashed before storage.
 * `traceId` is shared by all notifications created from this bulk request.
 */
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
        public ?string $traceId = null,
    ) {}
}
