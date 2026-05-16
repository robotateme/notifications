<?php

namespace Domain\Notifications;

enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';
}
