<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Domain\Notifications\Notification;

interface NotificationRepository
{
    /**
     * Find a notification by public UUID exposed by the API.
     */
    public function findByPublicId(string $publicId): ?Notification;

    /**
     * Find a notification by stored SHA-256 idempotency fingerprint.
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?Notification;

    /**
     * Query subscriber history ordered by most recent first.
     *
     * @return iterable<int, Notification>
     */
    public function findBySubscriberId(string $subscriberId): iterable;

    public function add(Notification $notification): Notification;

    public function save(Notification $notification): void;
}
