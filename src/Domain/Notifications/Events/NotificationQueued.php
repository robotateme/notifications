<?php

declare(strict_types=1);

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
        public ?string $traceId,
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

    public function aggregateId(): string
    {
        return $this->notificationId;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
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
            'trace_id' => $this->traceId,
            'channel' => $this->channel->value,
            'priority' => $this->priority->value,
        ];
    }
}
