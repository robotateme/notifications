<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\NotificationDeliveryGateway;
use Application\Notifications\Ports\NotificationRepository;
use Throwable;

class SendQueuedNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationDeliveryGateway $delivery,
    ) {}

    public function handle(int $notificationId): void
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
        } catch (Throwable $exception) {
            $notification->markFailed($exception->getMessage());
            $this->notifications->save($notification);

            throw $exception;
        }
    }
}
