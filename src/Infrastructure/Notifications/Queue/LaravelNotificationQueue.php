<?php

namespace Infrastructure\Notifications\Queue;

use App\Jobs\SendNotificationJob;
use Application\Notifications\Ports\NotificationQueue;
use Domain\Notifications\Notification;
use LogicException;

class LaravelNotificationQueue implements NotificationQueue
{
    public function enqueue(Notification $notification): void
    {
        if ($notification->internalId === null) {
            throw new LogicException('Notification must be persisted before enqueueing.');
        }

        $queue = $notification->isTransactional() ? 'notifications-high' : 'notifications';

        SendNotificationJob::dispatch($notification->internalId)->onQueue($queue);
    }
}
