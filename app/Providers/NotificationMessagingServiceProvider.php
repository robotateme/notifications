<?php

namespace App\Providers;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\MessageBroker;
use Application\Notifications\Ports\NotificationQueue;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notifications\Events\KcatMessageBroker;
use Infrastructure\Notifications\Events\LogMessageBroker;
use Infrastructure\Notifications\Events\OutboxDomainEventPublisher;
use Infrastructure\Notifications\Queue\LaravelNotificationQueue;

final class NotificationMessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationQueue::class, LaravelNotificationQueue::class);
        $this->app->bind(DomainEventPublisher::class, OutboxDomainEventPublisher::class);
        $this->app->bind(MessageBroker::class, function ($app): MessageBroker {
            if (config('kafka.publisher') === 'kcat') {
                return $app->make(KcatMessageBroker::class);
            }

            return $app->make(LogMessageBroker::class);
        });
    }
}
