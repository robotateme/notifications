<?php

namespace Domain\Shared;

interface DomainEvent
{
    public function eventId(): string;

    public function name(): string;

    public function aggregateId(): string;

    public function traceId(): ?string;

    public function occurredAt(): Timestamp;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
