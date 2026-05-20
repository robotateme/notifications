<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Domain\Notifications\Notification;

interface NotificationDeliveryGateway
{
    public function send(Notification $notification, string $idempotencyKey): void;
}
