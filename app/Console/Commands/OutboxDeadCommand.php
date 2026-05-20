<?php

namespace App\Console\Commands;

use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Console\Command;

final class OutboxDeadCommand extends Command
{
    protected $signature = 'outbox:dead {--limit=50} {--page=1}';

    protected $description = 'List dead outbox messages';

    public function handle(OutboxMessageRepository $outbox): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $page = max(1, (int) $this->option('page'));
        $offset = ($page - 1) * $limit;
        $total = $outbox->deadCount();
        $rows = [];

        foreach ($outbox->dead($limit, $offset) as $message) {
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
            $this->info($total === 0 ? 'No dead outbox messages.' : 'No dead outbox messages on this page.');

            return self::SUCCESS;
        }

        $this->info("Dead outbox messages: {$total}. Page {$page}, limit {$limit}.");
        $this->table(
            ['ID', 'Event ID', 'Topic', 'Event', 'Aggregate ID', 'Attempts', 'Last Error'],
            $rows,
        );

        return self::SUCCESS;
    }
}
