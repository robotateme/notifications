<?php

namespace Application\Notifications;

use Domain\Notifications\Notification;

final class NotificationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'idempotency_key' => $notification->idempotencyKey,
            'subscriber_id' => $notification->subscriberId,
            'channel' => $notification->channel->value,
            'priority' => $notification->priority->value,
            'recipient' => $notification->recipient,
            'subject' => $notification->subject,
            'body' => $notification->body,
            'payload' => $notification->payload?->toArray(),
            'status' => $notification->status->value,
            'attempts' => $notification->attempts,
            'queued_at' => $notification->queuedAt->toAtom(),
            'processing_at' => $notification->processingAt?->toAtom(),
            'sent_at' => $notification->sentAt?->toAtom(),
            'delivered_at' => $notification->deliveredAt?->toAtom(),
            'dropped_at' => $notification->droppedAt?->toAtom(),
            'last_error' => $notification->lastError,
        ];
    }
}
