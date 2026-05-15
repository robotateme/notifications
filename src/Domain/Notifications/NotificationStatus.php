<?php

namespace Domain\Notifications;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}
