<?php

declare(strict_types=1);

namespace App\Jobs;

use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly ?string $traceId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SendQueuedNotificationHandler $handler): void
    {
        $handler->handle(
            notificationId: $this->notificationId,
            dropOnFailure: $this->attempts() >= $this->tries,
        );
    }
}
