<?php

namespace Application\Notifications\Ports;

use Domain\Notifications\Notification;

interface NotificationRepository
{
    public function findByPublicId(string $publicId): ?Notification;

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification;

    /**
     * @return iterable<int, Notification>
     */
    public function findBySubscriberId(string $subscriberId): iterable;

    public function add(Notification $notification): Notification;

    public function save(Notification $notification): void;
}
