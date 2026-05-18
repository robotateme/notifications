<?php

namespace Application\Notifications\Ports;

interface NotificationIdGenerator
{
    public function generate(): string;
}
