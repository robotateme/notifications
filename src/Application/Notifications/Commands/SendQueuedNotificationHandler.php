<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;
use Throwable;

class SendQueuedNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationDeliveryGateway $delivery,
        private readonly DomainEventPublisher $events,
    ) {}

    public function handle(int $notificationId, bool $rethrow = true, bool $dropOnFailure = false): void
    {
        $notification = $this->notifications->get($notificationId);

        if ($notification->wasSent()) {
            return;
        }

        $notification->markProcessing();
        $this->notifications->save($notification);

        try {
            $this->delivery->send($notification);
            $notification->markSent();
            $this->notifications->save($notification);
            $this->publishRecordedEvents($notification);
        } catch (Throwable $exception) {
            $notification->recordDeliveryFailure($exception->getMessage());

            if ($dropOnFailure) {
                $notification->markDropped($exception->getMessage());
            }

            $this->notifications->save($notification);
            $this->publishRecordedEvents($notification);

            if ($rethrow) {
                throw $exception;
            }
        }
    }

    private function publishRecordedEvents(Notification $notification): void
    {
        foreach ($notification->releaseDomainEvents() as $event) {
            $this->events->publish($event);
        }
    }
}
