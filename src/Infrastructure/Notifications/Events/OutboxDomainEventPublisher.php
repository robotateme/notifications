<?php

namespace Infrastructure\Notifications\Events;

use Application\Notifications\Ports\DomainEventPublisher;
use Application\Notifications\Ports\OutboxMessageRepository;
use Domain\Shared\DomainEvent;

final class OutboxDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private readonly OutboxMessageRepository $outbox) {}

    public function publish(DomainEvent $event): void
    {
        $this->outbox->add($event, config('kafka.notifications_topic'));
    }
}
