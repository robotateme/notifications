<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Notifications\Persistence\Casts\TimestampCast;

#[Fillable([
    'event_id',
    'topic',
    'event_name',
    'aggregate_id',
    'trace_id',
    'payload',
    'status',
    'attempts',
    'available_at',
    'published_at',
    'last_error',
])]
class OutboxMessage extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => TimestampCast::class,
            'published_at' => TimestampCast::class,
        ];
    }
}
