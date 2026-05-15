<?php

namespace Application\Notifications\Queries;

use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;

class GetNotificationHandler
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function handle(string $publicId): ?Notification
    {
        return $this->notifications->findByPublicId($publicId);
    }
}
