<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\TransactionManager;
use Domain\Notifications\Notification;
use Throwable;

final class SendQueuedNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationDeliveryGateway $delivery,
        private readonly DomainEventPublisher $events,
        private readonly TransactionManager $transactions,
    ) {}

    public function handle(string $notificationId, bool $rethrow = true, bool $dropOnFailure = false): void
    {
        $notification = $this->notifications->findByPublicId($notificationId);

        if ($notification === null) {
            return;
        }

        if ($notification->wasSent()) {
            return;
        }

        $notification->markProcessing();
        $this->notifications->save($notification);

        try {
            $this->delivery->send($notification, $this->deliveryIdempotencyKey($notification));
        } catch (Throwable $exception) {
            $notification->recordDeliveryFailure($exception->getMessage());

            if ($dropOnFailure) {
                $notification->markDropped($exception->getMessage());
            }

            $this->saveWithRecordedEvents($notification);

            if ($rethrow) {
                throw $exception;
            }

            return;
        }

        $notification->markSent();
        $this->saveWithRecordedEvents($notification);
    }

    private function deliveryIdempotencyKey(Notification $notification): string
    {
        return "notification-delivery:{$notification->id}";
    }

    private function saveWithRecordedEvents(Notification $notification): void
    {
        $this->transactions->run(function () use ($notification): void {
            $this->notifications->save($notification);

            foreach ($notification->releaseDomainEvents() as $event) {
                $this->events->publish($event);
            }
        });
    }
}
