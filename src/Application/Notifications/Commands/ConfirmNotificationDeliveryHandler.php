<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationStatus;

final class ConfirmNotificationDeliveryHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly DomainEventPublisher $events,
    ) {}

    public function handle(ConfirmNotificationDeliveryCommand $command): ?Notification
    {
        $notification = $this->notifications->findByPublicId($command->notificationId);

        if ($notification === null) {
            return null;
        }

        match ($command->status) {
            NotificationStatus::Delivered => $notification->markDelivered(),
            NotificationStatus::Dropped => $notification->markDropped($command->error ?? 'Delivery provider dropped the message.'),
            default => null,
        };

        $this->notifications->save($notification);

        foreach ($notification->releaseDomainEvents() as $event) {
            $this->events->publish($event);
        }

        return $notification;
    }
}
