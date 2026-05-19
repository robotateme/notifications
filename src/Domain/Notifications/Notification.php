<?php

namespace Domain\Notifications;

use Domain\Notifications\Events\NotificationDelivered;
use Domain\Notifications\Events\NotificationDropped;
use Domain\Notifications\Events\NotificationQueued;
use Domain\Notifications\Events\NotificationSent;
use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;
use Webmozart\Assert\Assert;

final class Notification
{
    /**
     * @var array<int, DomainEvent>
     */
    private array $domainEvents = [];

    public function __construct(
        public string $id,
        public ?string $idempotencyKey,
        public string $subscriberId,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $recipient,
        public ?string $subject,
        public ?string $body,
        public ?NotificationPayload $payload,
        public NotificationStatus $status,
        public int $attempts,
        public Timestamp $queuedAt,
        public ?Timestamp $processingAt = null,
        public ?Timestamp $sentAt = null,
        public ?Timestamp $deliveredAt = null,
        public ?Timestamp $droppedAt = null,
        public ?string $lastError = null,
    ) {}

    public static function queue(
        string $id,
        ?string $idempotencyKey,
        string $subscriberId,
        NotificationChannel $channel,
        NotificationPriority $priority,
        string $recipient,
        ?string $subject,
        ?string $body,
        ?array $payload,
    ): self {
        Assert::notEmpty($subscriberId, 'Subscriber id is required.');
        Assert::notEmpty($recipient, 'Recipient is required.');
        Assert::true($body !== null || $payload !== null, 'Notification body or payload is required.');

        if ($idempotencyKey !== null) {
            Assert::notEmpty($idempotencyKey, 'Idempotency key must not be empty.');
        }

        if ($body !== null) {
            Assert::notEmpty($body, 'Notification body must not be empty.');
        }

        $occurredAt = Timestamp::now();

        $notification = new self(
            id: NotificationId::fromString($id)->value(),
            idempotencyKey: $idempotencyKey,
            subscriberId: $subscriberId,
            channel: $channel,
            priority: $priority,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            payload: NotificationPayload::fromNullableArray($payload),
            status: NotificationStatus::Queued,
            attempts: 0,
            queuedAt: $occurredAt,
        );

        $notification->record(new NotificationQueued(
            notificationId: $notification->id,
            subscriberId: $notification->subscriberId,
            channel: $notification->channel,
            priority: $notification->priority,
            occurredAt: $occurredAt,
        ));

        return $notification;
    }

    public function markProcessing(): void
    {
        $this->attempts++;
        $this->processingAt = Timestamp::now();
        $this->lastError = null;
    }

    public function markSent(): void
    {
        $occurredAt = Timestamp::now();

        $this->status = NotificationStatus::Sent;
        $this->sentAt = $occurredAt;
        $this->droppedAt = null;
        $this->lastError = null;

        $this->record(new NotificationSent(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            channel: $this->channel,
            occurredAt: $occurredAt,
        ));
    }

    public function markDelivered(): void
    {
        $occurredAt = Timestamp::now();

        $this->status = NotificationStatus::Delivered;
        $this->deliveredAt = $occurredAt;
        $this->droppedAt = null;
        $this->lastError = null;

        $this->record(new NotificationDelivered(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            occurredAt: $occurredAt,
        ));
    }

    public function recordDeliveryFailure(string $error): void
    {
        $this->lastError = $error;
    }

    public function markDropped(string $error): void
    {
        $this->status = NotificationStatus::Dropped;
        $occurredAt = Timestamp::now();
        $this->droppedAt = $occurredAt;
        $this->lastError = $error;

        $this->record(new NotificationDropped(
            notificationId: $this->id,
            subscriberId: $this->subscriberId,
            reason: $error,
            occurredAt: $occurredAt,
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
