<?php

declare(strict_types=1);

namespace Application\Notifications\Ports;

use Closure;

interface TransactionManager
{
    public function run(Closure $callback): mixed;
}
