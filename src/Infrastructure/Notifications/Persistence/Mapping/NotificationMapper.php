<?php

namespace Infrastructure\Notifications\Persistence\Mapping;

use App\Models\NotificationMessage;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationId;
use Domain\Notifications\NotificationPriority;
use Domain\Notifications\NotificationStatus;

final class NotificationMapper
{
    public function toDomain(NotificationMessage $model): Notification
    {
        return new Notification(
            id: NotificationId::fromString($model->uuid)->value(),
            idempotencyKey: $model->idempotency_key,
            subscriberId: $model->subscriber_id,
            channel: NotificationChannel::from($model->channel),
            priority: NotificationPriority::from($model->priority),
            recipient: $model->recipient,
            subject: $model->subject,
            body: $model->body,
            payload: $model->payload,
            status: NotificationStatus::from($model->status),
            attempts: $model->attempts,
            queuedAt: $model->queued_at,
            processingAt: $model->processing_at,
            sentAt: $model->sent_at,
            deliveredAt: $model->delivered_at,
            droppedAt: $model->dropped_at,
            lastError: $model->last_error,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPersistence(Notification $notification): array
    {
        return [
            'uuid' => $notification->id,
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
            'queued_at' => $notification->queuedAt,
            'processing_at' => $notification->processingAt,
            'sent_at' => $notification->sentAt,
            'delivered_at' => $notification->deliveredAt,
            'dropped_at' => $notification->droppedAt,
            'last_error' => $notification->lastError,
        ];
    }
}
