<?php

namespace App\Console\Commands;

use Application\Notifications\Commands\PublishOutboxMessagesHandler;
use Illuminate\Console\Command;

final class OutboxPublishCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description = 'Publish pending outbox messages to the configured broker';

    public function handle(PublishOutboxMessagesHandler $handler): int
    {
        $published = $handler->handle((int) $this->option('limit'));

        $this->info("Published {$published} outbox message(s).");

        return self::SUCCESS;
    }
}
