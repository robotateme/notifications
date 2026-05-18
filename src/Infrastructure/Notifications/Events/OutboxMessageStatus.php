<?php

namespace Infrastructure\Notifications\Events;

enum OutboxMessageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';
}
