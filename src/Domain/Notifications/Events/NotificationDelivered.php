<?php

namespace Domain\Notifications\Events;

use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;

final readonly class NotificationDelivered implements DomainEvent
{
    public function __construct(
        public string $notificationId,
        public string $subscriberId,
        public ?string $traceId,
        private Timestamp $occurredAt,
    ) {}

    public function eventId(): string
    {
        return "{$this->notificationId}:{$this->name()}";
    }

    public function name(): string
    {
        return 'notification.delivered';
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
        ];
    }
}
