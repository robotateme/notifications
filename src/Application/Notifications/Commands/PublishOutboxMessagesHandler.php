<?php

namespace Application\Notifications\Commands;

use Application\Notifications\Ports\MessageBroker;
use Application\Notifications\Ports\OutboxMessageRepository;
use Throwable;

final class PublishOutboxMessagesHandler
{
    public function __construct(
        private readonly OutboxMessageRepository $outbox,
        private readonly MessageBroker $broker,
    ) {}

    public function handle(int $limit = 100): int
    {
        $published = 0;

        foreach ($this->outbox->pending($limit) as $message) {
            try {
                $this->broker->publish(
                    topic: $message->topic,
                    key: $message->aggregateId,
                    payload: $message->payload,
                );

                $this->outbox->markPublished($message->id);
                $published++;
            } catch (Throwable $exception) {
                $this->outbox->markFailed($message->id, $exception->getMessage());
            }
        }

        return $published;
    }
}
