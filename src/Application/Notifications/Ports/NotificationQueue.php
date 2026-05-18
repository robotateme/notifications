<?php

namespace Application\Notifications\Ports;

use Domain\Notifications\NotificationPriority;

interface NotificationQueue
{
    public function enqueue(string $notificationId, NotificationPriority $priority): void;
}
