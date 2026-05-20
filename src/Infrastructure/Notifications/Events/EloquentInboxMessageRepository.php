<?php

namespace Infrastructure\Notifications\Events;

use App\Models\InboxMessage;
use Application\Notifications\Inbox\IncomingMessage;
use Application\Notifications\Ports\InboxMessageRepository;
use Closure;
use Domain\Shared\Timestamp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EloquentInboxMessageRepository implements InboxMessageRepository
{
    public function handleOnce(IncomingMessage $message, Closure $handler): bool
    {
        $inbox = $this->claim($message);

        if ($inbox === null) {
            return false;
        }

        try {
            $handler($message);

            $inbox->forceFill([
                'status' => InboxMessageStatus::Processed->value,
                'processed_at' => Timestamp::now()->toDatabaseString(),
                'last_error' => null,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $inbox->forceFill([
                'status' => InboxMessageStatus::Failed->value,
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function claim(IncomingMessage $message): ?InboxMessage
    {
        return DB::transaction(function () use ($message): ?InboxMessage {
            $inbox = InboxMessage::query()
                ->where('event_id', $message->eventId)
                ->where('consumer_name', $message->consumerName)
                ->lockForUpdate()
                ->first();

            if ($inbox !== null) {
                if (in_array($inbox->status, [
                    InboxMessageStatus::Processing->value,
                    InboxMessageStatus::Processed->value,
                ], true)) {
                    return null;
                }

                $inbox->forceFill([
                    'status' => InboxMessageStatus::Processing->value,
                    'last_error' => null,
                ])->save();

                return $inbox;
            }

            try {
                return InboxMessage::query()->create([
                    'event_id' => $message->eventId,
                    'consumer_name' => $message->consumerName,
                    'topic' => $message->topic,
                    'message_key' => $message->key,
                    'payload' => $message->payload,
                    'status' => InboxMessageStatus::Processing->value,
                ]);
            } catch (QueryException) {
                return null;
            }
        });
    }
}
