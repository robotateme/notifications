<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'notifications_topic' => env('KAFKA_NOTIFICATIONS_TOPIC', 'notifications.events'),
];
