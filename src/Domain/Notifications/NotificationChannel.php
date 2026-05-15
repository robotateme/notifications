<?php

namespace Domain\Notifications;

enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';
}
