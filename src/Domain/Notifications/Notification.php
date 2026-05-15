<?php

namespace Domain\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Notification
{
    public function __construct(
        public ?int $internalId,
        public string $id,
        public ?string $idempotencyKey,
        public NotificationChannel $channel,
        public string $recipient,
        public ?string $subject,
        public ?string $body,
        public ?array $payload,
        public NotificationStatus $status,
        public int $attempts,
        public Carbon $queuedAt,
        public ?Carbon $processingAt = null,
        public ?Carbon $sentAt = null,
        public ?Carbon $failedAt = null,
        public ?string $lastError = null,
    ) {}

    public static function queue(
        ?string $idempotencyKey,
        NotificationChannel $channel,
        string $recipient,
        ?string $subject,
        ?string $body,
        ?array $payload,
    ): self {
        return new self(
            internalId: null,
            id: (string) Str::uuid(),
            idempotencyKey: $idempotencyKey,
            channel: $channel,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            payload: $payload,
            status: NotificationStatus::Queued,
            attempts: 0,
            queuedAt: now(),
        );
    }

    public function markProcessing(): void
    {
        $this->status = NotificationStatus::Processing;
        $this->attempts++;
        $this->processingAt = now();
        $this->lastError = null;
    }

    public function markSent(): void
    {
        $this->status = NotificationStatus::Sent;
        $this->sentAt = now();
        $this->failedAt = null;
        $this->lastError = null;
    }

    public function markFailed(string $error): void
    {
        $this->status = NotificationStatus::Failed;
        $this->failedAt = now();
        $this->lastError = $error;
    }

    public function wasSent(): bool
    {
        return $this->status === NotificationStatus::Sent;
    }
}
