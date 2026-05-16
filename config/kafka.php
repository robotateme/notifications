<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'publisher' => env('KAFKA_PUBLISHER', 'rest'),
    'rest_url' => env('KAFKA_REST_URL', 'http://kafka-rest:8082'),
    'notifications_topic' => env('KAFKA_NOTIFICATIONS_TOPIC', 'notifications.events'),
];
