<?php

declare(strict_types=1);

namespace Domain\Notifications;

/**
 * Supported delivery channels accepted by the public API and delivery gateways.
 *
 * Values: email, sms, push.
 */
enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
}
