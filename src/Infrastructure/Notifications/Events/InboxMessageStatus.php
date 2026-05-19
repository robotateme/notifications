<?php

namespace Infrastructure\Notifications\Events;

enum InboxMessageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
