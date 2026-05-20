<?php

declare(strict_types=1);

namespace Domain\Notifications;

/**
 * Business priority used to route notifications to different queues.
 *
 * Values:
 * - transactional: urgent traffic, routed to the high priority queue;
 * - marketing: regular bulk traffic, routed to the default queue.
 */
enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';
}
