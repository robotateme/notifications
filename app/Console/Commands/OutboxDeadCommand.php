<?php

namespace App\Console\Commands;

use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Console\Command;

final class OutboxDeadCommand extends Command
{
    protected $signature = 'outbox:dead {--limit=50}';

    protected $description = 'List dead outbox messages';

    public function handle(OutboxMessageRepository $outbox): int
    {
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

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Event ID', 'Topic', 'Event', 'Aggregate ID', 'Attempts', 'Last Error'],
            $rows,
        );

        return self::SUCCESS;
    }
}
