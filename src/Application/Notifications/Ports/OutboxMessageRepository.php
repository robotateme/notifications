<?php

namespace Application\Notifications\Ports;

use Application\Notifications\Outbox\DeadOutboxMessage;
use Application\Notifications\Outbox\PendingOutboxMessage;
use Domain\Shared\DomainEvent;

interface OutboxMessageRepository
{
    public function add(DomainEvent $event, string $topic): void;

    /**
     * @return iterable<int, PendingOutboxMessage>
     */
    public function pending(int $limit): iterable;

    public function markPublished(int $id): void;

    public function markFailed(int $id, string $error): void;

    /**
     * @return iterable<int, DeadOutboxMessage>
     */
    public function dead(int $limit, int $offset = 0): iterable;

    public function deadCount(): int;

    public function retryDead(int $id): bool;
}
