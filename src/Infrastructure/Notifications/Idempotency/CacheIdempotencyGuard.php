<?php

namespace Infrastructure\Notifications\Idempotency;

use Application\Notifications\Ports\IdempotencyGuard;
use Closure;
use Illuminate\Support\Facades\Cache;

class CacheIdempotencyGuard implements IdempotencyGuard
{
    public function run(string $key, Closure $callback): mixed
    {
        return Cache::lock("notifications:idempotency:{$key}", 10)
            ->block(3, $callback);
    }
}
