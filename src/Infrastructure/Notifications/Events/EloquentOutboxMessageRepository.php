<?php

namespace Infrastructure\Notifications\Events;

use App\Models\OutboxMessage;
use Application\Notifications\Outbox\PendingOutboxMessage;
use Application\Notifications\Ports\OutboxMessageRepository;
use Domain\Shared\DomainEvent;
use Domain\Shared\Timestamp;
use Illuminate\Support\Facades\DB;

final class EloquentOutboxMessageRepository implements OutboxMessageRepository
{
    private const MAX_ATTEMPTS = 5;

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
        $ids = DB::transaction(function () use ($limit): array {
            $now = Timestamp::now();
            $claimUntil = $now->plusSeconds(60);

            $ids = OutboxMessage::query()
                ->whereIn('status', [
                    OutboxMessageStatus::Pending->value,
                    OutboxMessageStatus::Failed->value,
                    OutboxMessageStatus::Processing->value,
                ])
                ->where(fn ($query) => $query
                    ->whereNull('available_at')
                    ->orWhere('available_at', '<=', $now->toDatabaseString()))
                ->orderBy('id')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                return [];
            }

            OutboxMessage::query()
                ->whereKey($ids)
                ->update([
                    'status' => OutboxMessageStatus::Processing->value,
                    'available_at' => $claimUntil->toDatabaseString(),
                ]);

            return $ids;
        });

        if ($ids === []) {
            return [];
        }

        return OutboxMessage::query()
            ->whereKey($ids)
            ->where('status', OutboxMessageStatus::Processing->value)
            ->orderBy('id')
            ->lazy()
            ->map(fn (OutboxMessage $message): PendingOutboxMessage => new PendingOutboxMessage(
                id: $message->id,
                topic: $message->topic,
                aggregateId: $message->aggregate_id,
                payload: $message->payload,
            ));
    }

    public function markPublished(int $id): void
    {
        OutboxMessage::query()
            ->whereKey($id)
            ->where('status', OutboxMessageStatus::Processing->value)
            ->update([
                'status' => OutboxMessageStatus::Published->value,
                'published_at' => Timestamp::now()->toDatabaseString(),
                'available_at' => null,
                'last_error' => null,
            ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $message = OutboxMessage::query()->findOrFail($id);
        $attempts = $message->attempts + 1;
        $isDead = $attempts >= self::MAX_ATTEMPTS;

        $message->forceFill([
            'status' => $isDead ? OutboxMessageStatus::Dead->value : OutboxMessageStatus::Failed->value,
            'attempts' => $attempts,
            'available_at' => $isDead ? null : Timestamp::now()->plusSeconds(min(300, 10 * $attempts)),
            'last_error' => $error,
        ])->save();
    }
}
