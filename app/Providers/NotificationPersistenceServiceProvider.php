<?php

namespace App\Providers;

use Application\Notifications\Ports\IdempotencyGuard;
use Application\Notifications\Ports\InboxMessageRepository;
use Application\Notifications\Ports\NotificationIdGenerator;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\OutboxMessageRepository;
use Application\Notifications\Ports\TransactionManager;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notifications\Events\EloquentInboxMessageRepository;
use Infrastructure\Notifications\Events\EloquentOutboxMessageRepository;
use Infrastructure\Notifications\Identity\UuidNotificationIdGenerator;
use Infrastructure\Notifications\Idempotency\CacheIdempotencyGuard;
use Infrastructure\Notifications\Persistence\DatabaseTransactionManager;
use Infrastructure\Notifications\Persistence\EloquentNotificationRepository;

final class NotificationPersistenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationIdGenerator::class, UuidNotificationIdGenerator::class);
        $this->app->bind(OutboxMessageRepository::class, EloquentOutboxMessageRepository::class);
        $this->app->bind(InboxMessageRepository::class, EloquentInboxMessageRepository::class);
        $this->app->bind(IdempotencyGuard::class, CacheIdempotencyGuard::class);
        $this->app->bind(TransactionManager::class, DatabaseTransactionManager::class);
    }
}
