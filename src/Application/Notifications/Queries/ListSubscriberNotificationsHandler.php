<?php

namespace Application\Notifications\Queries;

use Application\Notifications\Ports\NotificationRepository;
use Domain\Notifications\Notification;

class ListSubscriberNotificationsHandler
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    /**
     * @return array<int, Notification>
     */
    public function handle(string $subscriberId): array
    {
        return $this->notifications->findBySubscriberId($subscriberId);
    }
}
