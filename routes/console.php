<?php

use Application\Notifications\Commands\PublishOutboxMessagesHandler;
use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('outbox:publish {--limit=100}', function (PublishOutboxMessagesHandler $handler) {
    $published = $handler->handle((int) $this->option('limit'));

    $this->info("Published {$published} outbox message(s).");
})->purpose('Publish pending outbox messages to the configured broker');

Artisan::command('outbox:dead {--limit=50}', function (OutboxMessageRepository $outbox) {
    $rows = [];

    foreach ($outbox->dead((int) $this->option('limit')) as $message) {
        $rows[] = [
            $message->id,
            $message->eventId,
            $message->topic,
            $message->eventName,
            $message->aggregateId,
            $message->attempts,
            $message->lastError,
        ];
    }

    if ($rows === []) {
        $this->info('No dead outbox messages.');

        return;
    }

    $this->table(
        ['ID', 'Event ID', 'Topic', 'Event', 'Aggregate ID', 'Attempts', 'Last Error'],
        $rows,
    );
})->purpose('List dead outbox messages');

Artisan::command('outbox:retry-dead {id}', function (OutboxMessageRepository $outbox) {
    $id = (int) $this->argument('id');

    if (! $outbox->retryDead($id)) {
        $this->error("Dead outbox message {$id} was not found.");

        return 1;
    }

    $this->info("Dead outbox message {$id} was returned to pending status.");
})->purpose('Return a dead outbox message to pending status');
