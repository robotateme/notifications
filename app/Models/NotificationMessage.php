<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Notifications\Persistence\Casts\NotificationPayloadCast;
use Infrastructure\Notifications\Persistence\Casts\TimestampCast;

#[Fillable([
    'uuid',
    'idempotency_key',
    'trace_id',
    'subscriber_id',
    'channel',
    'priority',
    'recipient',
    'subject',
    'body',
    'payload',
    'status',
    'attempts',
    'queued_at',
    'processing_at',
    'sent_at',
    'delivered_at',
    'dropped_at',
    'last_error',
])]
class NotificationMessage extends Model
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => NotificationPayloadCast::class,
            'queued_at' => TimestampCast::class,
            'processing_at' => TimestampCast::class,
            'sent_at' => TimestampCast::class,
            'delivered_at' => TimestampCast::class,
            'dropped_at' => TimestampCast::class,
        ];
    }
}
