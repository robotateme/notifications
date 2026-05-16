<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\Notification;

readonly class CreateBulkNotificationsResult
{
    /**
     * @param  array<int, Notification>  $notifications
     */
    public function __construct(public array $notifications) {}
}
