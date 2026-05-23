<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Infrastructure\Notifications\Persistence\Casts\NotificationPayloadCast;
use Infrastructure\Notifications\Persistence\Casts\TimestampCast;

/**
 * @property string $uuid
 * @property string|null $idempotency_key
 * @property string|null $trace_id
 * @property string $subscriber_id
 * @property string $channel
 * @property string $priority
 * @property string $recipient
 * @property string|null $subject
 * @property string|null $body
 * @property \Domain\Notifications\NotificationPayload|null $payload
 * @property string $status
 * @property int $attempts
 * @property \Domain\Shared\Timestamp $queued_at
 * @property \Domain\Shared\Timestamp|null $processing_at
 * @property \Domain\Shared\Timestamp|null $sent_at
 * @property \Domain\Shared\Timestamp|null $delivered_at
 * @property \Domain\Shared\Timestamp|null $dropped_at
 * @property string|null $last_error
 */
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
