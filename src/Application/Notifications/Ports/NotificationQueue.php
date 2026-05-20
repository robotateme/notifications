<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Domain\Notifications\NotificationPriority;

interface NotificationQueue
{
    public function enqueue(string $notificationId, NotificationPriority $priority, ?string $traceId): void;
}
