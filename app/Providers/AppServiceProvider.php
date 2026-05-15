<?php

namespace App\Providers;

use Application\Notifications\Ports\NotificationDeliveryGateway;
use Application\Notifications\Ports\NotificationQueue;
use Application\Notifications\Ports\NotificationRepository;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notifications\Delivery\LogNotificationDeliveryGateway;
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
