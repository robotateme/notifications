<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Application\Notifications\Ports\OutboxMessageRepository;
use Illuminate\Console\Command;

final class OutboxRetryDeadCommand extends Command
{
    protected $signature = 'outbox:retry-dead {id}';

    protected $description = 'Return a dead outbox message to pending status';

    public function handle(OutboxMessageRepository $outbox): int
    {
        $id = (int) $this->argument('id');

        if (! $outbox->retryDead($id)) {
            $this->error("Dead outbox message {$id} was not found.");

            return self::FAILURE;
        }

        $this->info("Dead outbox message {$id} was returned to pending status.");

        return self::SUCCESS;
    }
}
