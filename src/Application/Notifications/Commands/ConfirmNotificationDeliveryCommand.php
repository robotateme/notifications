<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationStatus;

readonly class ConfirmNotificationDeliveryCommand
{
    public function __construct(
        public string $notificationId,
        public NotificationStatus $status,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $notificationId, array $data): self
    {
        return new self(
            notificationId: $notificationId,
            status: NotificationStatus::from($data['status']),
            error: $data['error'] ?? null,
        );
    }
}
