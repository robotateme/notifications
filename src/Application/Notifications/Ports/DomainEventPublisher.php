<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Domain\Shared\DomainEvent;

interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
