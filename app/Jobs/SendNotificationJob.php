<?php

namespace App\Jobs;

use Application\Notifications\Commands\SendQueuedNotificationHandler;
use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\NotificationRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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

    public function failed(Throwable $exception): void
    {
        $notifications = app(NotificationRepository::class);
        $notification = $notifications->get($this->notificationMessageId);

        if ($notification->wasSent()) {
            return;
        }

        $notification->markDropped($exception->getMessage());
        $notifications->save($notification);

        $events = app(DomainEventPublisher::class);

        foreach ($notification->releaseDomainEvents() as $event) {
            $events->publish($event);
        }
    }
}
