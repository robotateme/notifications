<?php

declare(strict_types=1);

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'publisher' => env('KAFKA_PUBLISHER', 'kcat'),
    'notifications_topic' => env('KAFKA_NOTIFICATIONS_TOPIC', 'notifications.events'),
];
