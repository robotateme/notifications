<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Application\Notifications\Outbox\DeadOutboxMessage;
use Application\Notifications\Outbox\PendingOutboxMessage;
use Domain\Shared\DomainEvent;

interface OutboxMessageRepository
{
    public function add(DomainEvent $event, string $topic): void;

    /**
     * Atomically claim publishable messages for one publisher worker.
     *
     * @return iterable<int, PendingOutboxMessage>
     */
    public function pending(int $limit): iterable;

    public function markPublished(int $id): void;

    public function markFailed(int $id, string $error): void;

    /**
     * Query DLQ messages for operator review.
     *
     * @return iterable<int, DeadOutboxMessage>
     */
    public function dead(int $limit, int $offset = 0): iterable;

    /**
     * Count DLQ messages for CLI pagination and Prometheus metrics.
     */
    public function deadCount(): int;

    public function retryDead(int $id): bool;
}
