<?php

declare(strict_types=1);

namespace Application\Notifications\Commands;

use Application\Notifications\Inbox\IncomingMessage;
use Application\Notifications\Ports\InboxMessageRepository;
use Closure;

final class ProcessInboxMessageHandler
{
    public function __construct(private readonly InboxMessageRepository $inbox) {}

    public function handle(IncomingMessage $message, Closure $handler): bool
    {
        return $this->inbox->handleOnce($message, $handler);
    }
}
