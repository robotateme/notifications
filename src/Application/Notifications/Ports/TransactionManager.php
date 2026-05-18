<?php

namespace Application\Notifications\Ports;

use Closure;

interface TransactionManager
{
    public function run(Closure $callback): mixed;
}
