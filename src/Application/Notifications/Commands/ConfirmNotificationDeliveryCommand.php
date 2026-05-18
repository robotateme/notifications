<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationStatus;

final readonly class ConfirmNotificationDeliveryCommand
{
    public function __construct(
        public string $notificationId,
        public NotificationStatus $status,
        public ?string $error = null,
    ) {}
}
