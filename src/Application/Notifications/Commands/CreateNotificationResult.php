<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\Notification;

final readonly class CreateNotificationResult
{
    public function __construct(
        public Notification $notification,
        public bool $created,
    ) {}
}
