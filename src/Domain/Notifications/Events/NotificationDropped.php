<?php

namespace Domain\Notifications\Events;

use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;

final readonly class NotificationDropped implements DomainEvent
{
    public function __construct(
        public string $notificationId,
        public string $subscriberId,
        public string $reason,
        private Timestamp $occurredAt,
    ) {}

    public function eventId(): string
    {
        return "{$this->notificationId}:{$this->name()}";
    }

    public function name(): string
    {
        return 'notification.dropped';
    }

    public function occurredAt(): Timestamp
    {
        return $this->occurredAt;
    }

    public function payload(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'subscriber_id' => $this->subscriberId,
            'reason' => $this->reason,
        ];
    }
}
