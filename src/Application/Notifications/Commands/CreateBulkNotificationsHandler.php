<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\IdempotencyGuard;
use Application\Notifications\Ports\NotificationIdGenerator;
use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\TransactionManager;
use Domain\Notifications\Notification;

final class CreateBulkNotificationsHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationQueue $queue,
        private readonly DomainEventPublisher $events,
        private readonly IdempotencyGuard $idempotency,
        private readonly NotificationIdGenerator $ids,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return array<int, Notification>
     */
    public function handle(CreateBulkNotificationsCommand $command): array
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

        return $created;
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
            id: $this->ids->generate(),
            idempotencyKey: $idempotencyKey,
            subscriberId: $recipient,
            channel: $command->channel,
            priority: $command->priority,
            recipient: $recipient,
            subject: $command->subject,
            body: $command->body,
            payload: null,
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

        return $notification;
    }
}
