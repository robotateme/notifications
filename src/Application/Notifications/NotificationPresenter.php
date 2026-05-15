<?php

namespace Application\Notifications;

use Domain\Notifications\Notification;

class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'idempotency_key' => $notification->idempotencyKey,
            'channel' => $notification->channel->value,
            'recipient' => $notification->recipient,
            'subject' => $notification->subject,
            'body' => $notification->body,
            'payload' => $notification->payload,
            'status' => $notification->status->value,
            'attempts' => $notification->attempts,
            'queued_at' => $notification->queuedAt->toISOString(),
            'processing_at' => $notification->processingAt?->toISOString(),
            'sent_at' => $notification->sentAt?->toISOString(),
            'failed_at' => $notification->failedAt?->toISOString(),
            'last_error' => $notification->lastError,
        ];
    }
}
