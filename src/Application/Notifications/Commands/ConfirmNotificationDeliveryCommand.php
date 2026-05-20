<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationStatus;

/**
 * Command for applying a provider delivery callback.
 *
 * Only terminal provider statuses are expected here: delivered or dropped.
 */
final readonly class ConfirmNotificationDeliveryCommand
{
    public function __construct(
        public string $notificationId,
        public NotificationStatus $status,
        public ?string $error = null,
    ) {}
}
