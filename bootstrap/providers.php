<?php

use App\Providers\AppServiceProvider;
use App\Providers\NotificationDeliveryServiceProvider;
use App\Providers\NotificationMessagingServiceProvider;
use App\Providers\NotificationPersistenceServiceProvider;

return [
    AppServiceProvider::class,
    NotificationPersistenceServiceProvider::class,
    NotificationMessagingServiceProvider::class,
    NotificationDeliveryServiceProvider::class,
];
