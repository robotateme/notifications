<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;

class CreateNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationQueue $queue,
    ) {}

    public function handle(CreateNotificationCommand $command): CreateNotificationResult
    {
        if ($command->idempotencyKey !== null) {
            $existing = $this->notifications->findByIdempotencyKey($command->idempotencyKey);

            if ($existing !== null) {
                return new CreateNotificationResult($existing, false);
            }
        }

        $notification = Notification::queue(
            idempotencyKey: $command->idempotencyKey,
            channel: $command->channel,
            recipient: $command->recipient,
            subject: $command->subject,
            body: $command->body,
            payload: $command->payload,
        );

        $notification = $this->notifications->add($notification);
        $this->queue->enqueue($notification);

        return new CreateNotificationResult($notification, true);
    }
}
