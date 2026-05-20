<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Application\Notifications\Inbox\IncomingMessage;
use Closure;

interface InboxMessageRepository
{
    /**
     * Run handler once per event id and consumer name pair.
     *
     * Returns false when the message is already processing or processed.
     */
    public function handleOnce(IncomingMessage $message, Closure $handler): bool;
}
