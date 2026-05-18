<?php

namespace Application\Notifications\Ports;

use Domain\Shared\DomainEvent;

interface OutboxMessageRepository
{
    public function add(DomainEvent $event, string $topic): void;

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function pending(int $limit): iterable;

    public function markPublished(int $id): void;

    public function markFailed(int $id, string $error): void;
}
