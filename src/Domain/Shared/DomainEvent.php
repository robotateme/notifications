<?php

namespace Domain\Shared;

use Illuminate\Support\Carbon;

interface DomainEvent
{
    public function eventId(): string;

    public function name(): string;

    public function occurredAt(): Carbon;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
