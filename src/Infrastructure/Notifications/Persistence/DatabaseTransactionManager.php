<?php

namespace Infrastructure\Notifications\Persistence;

use Application\Notifications\Ports\TransactionManager;
use Closure;
use Illuminate\Support\Facades\DB;

final class DatabaseTransactionManager implements TransactionManager
{
    public function run(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
