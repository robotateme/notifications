<?php

namespace Infrastructure\Notifications\Events;

use App\Models\OutboxMessage;
use Application\Notifications\Ports\OutboxMessageRepository;
use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;

final class EloquentOutboxMessageRepository implements OutboxMessageRepository
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
                    'occurred_at' => $event->occurredAt()->toAtom(),
                    'data' => $event->payload(),
                ],
                'status' => OutboxMessageStatus::Pending->value,
                'available_at' => Timestamp::now(),
            ],
        );
    }

    public function pending(int $limit): iterable
    {
        return OutboxMessage::query()
            ->whereIn('status', [OutboxMessageStatus::Pending->value, OutboxMessageStatus::Failed->value])
            ->where(fn ($query) => $query
                ->whereNull('available_at')
                ->orWhere('available_at', '<=', Timestamp::now()->toDatabaseString()))
            ->orderBy('id')
            ->limit($limit)
            ->lazy()
            ->map(fn (OutboxMessage $message): array => [
                'id' => $message->id,
                'topic' => $message->topic,
                'aggregate_id' => $message->aggregate_id,
                'payload' => $message->payload,
            ]);
    }

    public function markPublished(int $id): void
    {
        OutboxMessage::query()
            ->whereKey($id)
            ->where('status', '!=', OutboxMessageStatus::Published->value)
            ->update([
                'status' => OutboxMessageStatus::Published->value,
                'published_at' => Timestamp::now()->toDatabaseString(),
                'last_error' => null,
            ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $message = OutboxMessage::query()->findOrFail($id);
        $attempts = $message->attempts + 1;

        $message->forceFill([
            'status' => OutboxMessageStatus::Failed->value,
            'attempts' => $attempts,
            'available_at' => Timestamp::now()->plusSeconds(min(300, 10 * $attempts)),
            'last_error' => $error,
        ])->save();
    }
}
