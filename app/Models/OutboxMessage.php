<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Notifications\Persistence\Casts\TimestampCast;

/**
 * @property int $id
 * @property string $event_id
 * @property string $topic
 * @property string $event_name
 * @property string $aggregate_id
 * @property string|null $trace_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property \Domain\Shared\Timestamp|null $available_at
 * @property \Domain\Shared\Timestamp|null $published_at
 * @property string|null $last_error
 */
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
