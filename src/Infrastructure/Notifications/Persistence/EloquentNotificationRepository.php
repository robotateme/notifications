<?php

namespace Infrastructure\Notifications\Persistence;

use App\Models\NotificationMessage;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Domain\Notifications\NotificationStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class EloquentNotificationRepository implements NotificationRepository
{
    public function get(int $id): Notification
    {
        $model = NotificationMessage::query()->find($id);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(NotificationMessage::class, [$id]);
        }

        return $this->toDomain($model);
    }

    public function findByPublicId(string $publicId): ?Notification
    {
        $model = NotificationMessage::query()
            ->where('uuid', $publicId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        $model = NotificationMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findBySubscriberId(string $subscriberId): array
    {
        return NotificationMessage::query()
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (NotificationMessage $model): Notification => $this->toDomain($model))
            ->all();
    }

    public function add(Notification $notification): Notification
    {
        $model = NotificationMessage::query()->create($this->toPersistence($notification));

        return $this->toDomain($model);
    }

    public function save(Notification $notification): void
    {
        $model = NotificationMessage::query()->findOrFail($notification->internalId);
        $model->forceFill($this->toPersistence($notification))->save();
    }

    private function toDomain(NotificationMessage $model): Notification
    {
        return new Notification(
            internalId: $model->id,
            id: $model->uuid,
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
            queuedAt: Carbon::parse($model->queued_at),
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
    private function toPersistence(Notification $notification): array
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
            'payload' => $notification->payload,
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
