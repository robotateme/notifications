<?php

namespace App\Jobs;

use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $notificationMessageId) {}

    /**
     * Execute the job.
     */
    public function handle(SendQueuedNotificationHandler $handler): void
    {
        $handler->handle($this->notificationMessageId);
    }
}
