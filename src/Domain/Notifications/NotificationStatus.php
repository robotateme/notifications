<?php

declare(strict_types=1);

namespace Domain\Notifications;

/**
 * Delivery lifecycle visible to API consumers.
 *
 * Values: queued, sent, delivered, dropped.
 */
enum NotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Dropped = 'dropped';
}
