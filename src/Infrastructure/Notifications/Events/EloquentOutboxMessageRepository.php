<?php

namespace Infrastructure\Notifications\Events;

use App\Models\OutboxMessage;
use Application\Notifications\Ports\OutboxMessageRepository;
use Domain\Shared\DomainEvent;
use Illuminate\Support\Carbon;

class EloquentOutboxMessageRepository implements OutboxMessageRepository
{
    public function add(DomainEvent $event, string $topic): void
    {
        OutboxMessage::query()->firstOrCreate(
            ['event_id' => $event->eventId()],
            [
                'topic' => $topic,
                'event_name' => $event->name(),
                'aggregate_id' => (string) $event->payload()['notification_id'],
                'payload' => [
                    'event_id' => $event->eventId(),
                    'event_name' => $event->name(),
                    'occurred_at' => $event->occurredAt()->toISOString(),
                    'data' => $event->payload(),
                ],
                'status' => OutboxMessage::STATUS_PENDING,
                'available_at' => now(),
            ],
        );
    }

    public function pending(int $limit): array
    {
        return OutboxMessage::query()
            ->whereIn('status', [OutboxMessage::STATUS_PENDING, OutboxMessage::STATUS_FAILED])
            ->where(fn ($query) => $query
                ->whereNull('available_at')
                ->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (OutboxMessage $message): array => [
                'id' => $message->id,
                'topic' => $message->topic,
                'aggregate_id' => $message->aggregate_id,
                'payload' => $message->payload,
            ])
            ->all();
    }

    public function markPublished(int $id): void
    {
        OutboxMessage::query()
            ->whereKey($id)
            ->where('status', '!=', OutboxMessage::STATUS_PUBLISHED)
            ->update([
                'status' => OutboxMessage::STATUS_PUBLISHED,
                'published_at' => now(),
                'last_error' => null,
            ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $message = OutboxMessage::query()->findOrFail($id);
        $attempts = $message->attempts + 1;

        $message->forceFill([
            'status' => OutboxMessage::STATUS_FAILED,
            'attempts' => $attempts,
            'available_at' => Carbon::now()->addSeconds(min(300, 10 * $attempts)),
            'last_error' => $error,
        ])->save();
    }
}
