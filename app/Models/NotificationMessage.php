<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'idempotency_key',
    'channel',
    'recipient',
    'subject',
    'body',
    'payload',
    'status',
    'attempts',
    'queued_at',
    'processing_at',
    'sent_at',
    'failed_at',
    'last_error',
])]
class NotificationMessage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

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
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NotificationMessage $message): void {
            $message->uuid ??= (string) Str::uuid();
            $message->status ??= self::STATUS_QUEUED;
            $message->attempts ??= 0;
            $message->queued_at ??= now();
        });
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
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
            'failed_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
