<?php

namespace Infrastructure\Notifications\Persistence;

use App\Models\NotificationMessage;
use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Infrastructure\Notifications\Persistence\Mapping\NotificationMapper;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function __construct(private readonly NotificationMapper $mapper) {}

    public function get(int $id): Notification
    {
        $model = NotificationMessage::query()->find($id);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(NotificationMessage::class, [$id]);
        }

        return $this->mapper->toDomain($model);
    }

    public function findByPublicId(string $publicId): ?Notification
    {
        $model = NotificationMessage::query()
            ->where('uuid', $publicId)
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        $model = NotificationMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findBySubscriberId(string $subscriberId): iterable
    {
        return NotificationMessage::query()
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('created_at')
            ->lazy()
            ->map(fn (NotificationMessage $model): Notification => $this->mapper->toDomain($model));
    }

    public function add(Notification $notification): Notification
    {
        $model = NotificationMessage::query()->create($this->mapper->toPersistence($notification));

        return $this->mapper->toDomain($model);
    }

    public function save(Notification $notification): void
    {
        $model = NotificationMessage::query()->findOrFail($notification->internalId);
        $model->forceFill($this->mapper->toPersistence($notification))->save();
    }
}
