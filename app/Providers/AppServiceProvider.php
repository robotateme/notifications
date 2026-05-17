<?php

namespace App\Providers;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\IdempotencyGuard;
use Application\Notifications\Ports\MessageBroker;
use Application\Notifications\Ports\NotificationDeliveryGateway;
use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notifications\Delivery\LogNotificationDeliveryGateway;
use Infrastructure\Notifications\Events\EloquentOutboxMessageRepository;
use Infrastructure\Notifications\Events\KcatMessageBroker;
use Infrastructure\Notifications\Events\LogMessageBroker;
use Infrastructure\Notifications\Events\OutboxDomainEventPublisher;
use Infrastructure\Notifications\Idempotency\CacheIdempotencyGuard;
use Infrastructure\Notifications\Persistence\EloquentNotificationRepository;
use Infrastructure\Notifications\Queue\LaravelNotificationQueue;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationQueue::class, LaravelNotificationQueue::class);
        $this->app->bind(DomainEventPublisher::class, OutboxDomainEventPublisher::class);
        $this->app->bind(OutboxMessageRepository::class, EloquentOutboxMessageRepository::class);
        $this->app->bind(IdempotencyGuard::class, CacheIdempotencyGuard::class);
        $this->app->bind(MessageBroker::class, function ($app): MessageBroker {
            if (config('kafka.publisher') === 'kcat') {
                return $app->make(KcatMessageBroker::class);
            }

            return $app->make(LogMessageBroker::class);
        });
        $this->app->bind(NotificationDeliveryGateway::class, LogNotificationDeliveryGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
