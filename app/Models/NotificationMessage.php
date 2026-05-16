<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'idempotency_key',
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
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_DROPPED = 'dropped';

    public const PRIORITY_TRANSACTIONAL = 'transactional';

    public const PRIORITY_MARKETING = 'marketing';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_PUSH = 'push';

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
            'payload' => 'array',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotificationMessage $message): void {
            $message->uuid ??= (string) Str::uuid();
            $message->subscriber_id ??= $message->recipient;
            $message->priority ??= self::PRIORITY_MARKETING;
            $message->status ??= self::STATUS_QUEUED;
            $message->attempts ??= 0;
            $message->queued_at ??= now();
        });
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'processing_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'dropped_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function markDropped(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_DROPPED,
            'dropped_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
