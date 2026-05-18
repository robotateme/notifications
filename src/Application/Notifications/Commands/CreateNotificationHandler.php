<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\IdempotencyGuard;
use Application\Notifications\Ports\NotificationIdGenerator;
use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\TransactionManager;
use Domain\Notifications\Notification;

final class CreateNotificationHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationQueue $queue,
        private readonly DomainEventPublisher $events,
        private readonly IdempotencyGuard $idempotency,
        private readonly NotificationIdGenerator $ids,
        private readonly TransactionManager $transactions,
    ) {}

    public function handle(CreateNotificationCommand $command): CreateNotificationResult
    {
        if ($command->idempotencyKey !== null) {
            return $this->idempotency->run(
                $command->idempotencyKey,
                fn (): CreateNotificationResult => $this->create($command),
            );
        }

        return $this->create($command);
    }

    private function create(CreateNotificationCommand $command): CreateNotificationResult
    {
        if ($command->idempotencyKey !== null) {
            $existing = $this->notifications->findByIdempotencyKey($command->idempotencyKey);

            if ($existing !== null) {
                return new CreateNotificationResult($existing, false);
            }
        }

        $notification = Notification::queue(
            id: $this->ids->generate(),
            idempotencyKey: $command->idempotencyKey,
            subscriberId: $command->subscriberId,
            channel: $command->channel,
            priority: $command->priority,
            recipient: $command->recipient,
            subject: $command->subject,
            body: $command->body,
            payload: $command->payload,
        );

        $notification = $this->transactions->run(function () use ($notification): Notification {
            $events = $notification->releaseDomainEvents();
            $notification = $this->notifications->add($notification);

            foreach ($events as $event) {
                $this->events->publish($event);
            }

            return $notification;
        });

        $this->queue->enqueue($notification->id, $notification->priority);

        return new CreateNotificationResult($notification, true);
    }
}
