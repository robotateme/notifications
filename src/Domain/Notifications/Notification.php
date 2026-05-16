<?php

namespace Domain\Notifications;

use Domain\Notifications\Events\NotificationDelivered;
use Domain\Notifications\Events\NotificationDropped;
use Domain\Notifications\Events\NotificationQueued;
use Domain\Notifications\Events\NotificationSent;
use Domain\Shared\DomainEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Notification
{
    /**
     * @var array<int, DomainEvent>
     */
    private array $domainEvents = [];

    public function __construct(
        public ?int $internalId,
        public string $id,
        public ?string $idempotencyKey,
        public string $subscriberId,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $recipient,
        public ?string $subject,
        public ?string $body,
        public ?array $payload,
        public NotificationStatus $status,
        public int $attempts,
        public Carbon $queuedAt,
        public ?Carbon $processingAt = null,
        public ?Carbon $sentAt = null,
        public ?Carbon $deliveredAt = null,
        public ?Carbon $droppedAt = null,
        public ?string $lastError = null,
    ) {}

    public static function queue(
        ?string $idempotencyKey,
        string $subscriberId,
        NotificationChannel $channel,
        NotificationPriority $priority,
        string $recipient,
        ?string $subject,
        ?string $body,
        ?array $payload,
    ): self {
        $notification = new self(
            internalId: null,
            id: (string) Str::uuid(),
            idempotencyKey: $idempotencyKey,
            subscriberId: $subscriberId,
            channel: $channel,
            priority: $priority,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            payload: $payload,
            status: NotificationStatus::Queued,
            attempts: 0,
            queuedAt: now(),
        );

        $notification->record(new NotificationQueued(
            notificationId: $notification->id,
            subscriberId: $notification->subscriberId,
            channel: $notification->channel,
            priority: $notification->priority,
            occurredAt: now(),
        ));

        return $notification;
    }

    public function markProcessing(): void
    {
        $this->attempts++;
        $this->processingAt = now();
        $this->lastError = null;
    }

    public function markSent(): void
    {
        $this->status = NotificationStatus::Sent;
        $this->sentAt = now();
        $this->droppedAt = null;
        $this->lastError = null;

        $this->record(new NotificationSent(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            channel: $this->channel,
            occurredAt: now(),
        ));
    }

    public function markDelivered(): void
    {
        $this->status = NotificationStatus::Delivered;
        $this->deliveredAt = now();
        $this->droppedAt = null;
        $this->lastError = null;

        $this->record(new NotificationDelivered(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            occurredAt: now(),
        ));
    }

    public function recordDeliveryFailure(string $error): void
    {
        $this->lastError = $error;
    }

    public function markDropped(string $error): void
    {
        $this->status = NotificationStatus::Dropped;
        $this->droppedAt = now();
        $this->lastError = $error;

        $this->record(new NotificationDropped(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            reason: $error,
            occurredAt: now(),
        ));
    }

    public function wasSent(): bool
    {
        return in_array($this->status, [NotificationStatus::Sent, NotificationStatus::Delivered], true);
    }

    public function isTransactional(): bool
    {
        return $this->priority === NotificationPriority::Transactional;
    }

    /**
     * @return array<int, DomainEvent>
     */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
