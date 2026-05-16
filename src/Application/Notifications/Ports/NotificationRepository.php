<?php

namespace Application\Notifications\Ports;

use Domain\Notifications\Notification;

interface NotificationRepository
{
    public function get(int $id): Notification;

    public function findByPublicId(string $publicId): ?Notification;

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification;

    /**
     * @return array<int, Notification>
     */
    public function findBySubscriberId(string $subscriberId): array;

    public function add(Notification $notification): Notification;

    public function save(Notification $notification): void;
}
