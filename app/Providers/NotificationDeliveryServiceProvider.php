<?php

namespace App\Providers;

use Application\Notifications\Ports\NotificationDeliveryGateway;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notifications\Delivery\LogNotificationDeliveryGateway;

final class NotificationDeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDeliveryGateway::class, LogNotificationDeliveryGateway::class);
    }
}
