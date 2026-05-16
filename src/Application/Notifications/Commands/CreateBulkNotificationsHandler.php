<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\IdempotencyGuard;
use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;

class CreateBulkNotificationsHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationQueue $queue,
        private readonly DomainEventPublisher $events,
        private readonly IdempotencyGuard $idempotency,
    ) {}

    public function handle(CreateBulkNotificationsCommand $command): CreateBulkNotificationsResult
    {
        $created = [];

        foreach ($command->recipients as $index => $recipient) {
            $idempotencyKey = $command->idempotencyKey === null
                ? null
                : "{$command->idempotencyKey}:{$index}:{$recipient}";

            $created[] = $idempotencyKey === null
                ? $this->createOne($command, $recipient, $idempotencyKey)
                : $this->idempotency->run(
                    $idempotencyKey,
                    fn (): Notification => $this->createOne($command, $recipient, $idempotencyKey),
                );
        }

        return new CreateBulkNotificationsResult($created);
    }

    private function createOne(
        CreateBulkNotificationsCommand $command,
        string $recipient,
        ?string $idempotencyKey,
    ): Notification {
        if ($idempotencyKey !== null) {
            $existing = $this->notifications->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }
        }

        $notification = Notification::queue(
            idempotencyKey: $idempotencyKey,
            subscriberId: $recipient,
            channel: $command->channel,
            priority: $command->priority,
            recipient: $recipient,
            subject: $command->subject,
            body: $command->body,
            payload: null,
        );

        $events = $notification->releaseDomainEvents();
        $notification = $this->notifications->add($notification);

        foreach ($events as $event) {
            $this->events->publish($event);
        }

        $this->queue->enqueue($notification);

        return $notification;
    }
}
