<?php

namespace Application\Notifications\Ports;

use Closure;

interface IdempotencyGuard
{
    public function run(string $key, Closure $callback): mixed;
}
