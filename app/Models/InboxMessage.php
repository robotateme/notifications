<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Notifications\Persistence\Casts\TimestampCast;

#[Fillable([
    'event_id',
    'topic',
    'message_key',
    'payload',
    'status',
    'processed_at',
    'last_error',
])]
class InboxMessage extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => TimestampCast::class,
        ];
    }
}
