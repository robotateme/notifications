<?php

namespace Application\Notifications\Ports;

use Application\Notifications\Inbox\IncomingMessage;
use Closure;

interface InboxMessageRepository
{
    public function handleOnce(IncomingMessage $message, Closure $handler): bool;
}
