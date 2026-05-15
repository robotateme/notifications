<?php

namespace Application\Notifications\Ports;

use Domain\Notifications\Notification;

interface NotificationQueue
{
    public function enqueue(Notification $notification): void;
}
