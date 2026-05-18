<?php

namespace Domain\Notifications\Events;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;

final readonly class NotificationQueued implements DomainEvent
{
    public function __construct(
        public string $notificationId,
        public string $subscriberId,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        private Timestamp $occurredAt,
    ) {}

    public function eventId(): string
    {
        return "{$this->notificationId}:{$this->name()}";
    }

    public function name(): string
    {
        return 'notification.queued';
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
            'channel' => $this->channel->value,
            'priority' => $this->priority->value,
        ];
    }
}
