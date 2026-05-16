<?php

use Application\Notifications\Commands\PublishOutboxMessagesHandler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('outbox:publish {--limit=100}', function (PublishOutboxMessagesHandler $handler) {
    $published = $handler->handle((int) $this->option('limit'));

    $this->info("Published {$published} outbox message(s).");
})->purpose('Publish pending outbox messages to the configured broker');
