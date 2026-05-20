<?php

namespace Infrastructure\Notifications\Queue;

use App\Jobs\SendNotificationJob;
use Application\Notifications\Ports\NotificationQueue;
use Domain\Notifications\NotificationPriority;

final class LaravelNotificationQueue implements NotificationQueue
{
    public function enqueue(string $notificationId, NotificationPriority $priority, ?string $traceId): void
    {
        $queue = $priority === NotificationPriority::Transactional ? 'notifications-high' : 'notifications';

        SendNotificationJob::dispatch($notificationId, $traceId)->onQueue($queue);
    }
}
