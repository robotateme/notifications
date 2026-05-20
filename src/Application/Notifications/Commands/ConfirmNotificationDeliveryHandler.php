<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\TransactionManager;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationStatus;

final class ConfirmNotificationDeliveryHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly DomainEventPublisher $events,
        private readonly TransactionManager $transactions,
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

        $this->transactions->run(function () use ($notification): void {
            $this->notifications->save($notification);

            foreach ($notification->releaseDomainEvents() as $event) {
                $this->events->publish($event);
            }
        });

        return $notification;
    }
}
