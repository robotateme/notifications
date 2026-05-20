<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

interface NotificationIdGenerator
{
    public function generate(): string;
}
