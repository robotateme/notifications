<?php

namespace Application\Notifications\Idempotency;

use InvalidArgumentException;

final class IdempotencyKeyFingerprint
{
    public const int MAX_EXTERNAL_LENGTH = 120;

    public const int LENGTH = 64;

    public static function fromExternal(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key must not be empty.');
        }

        if (strlen($key) > self::MAX_EXTERNAL_LENGTH) {
            throw new InvalidArgumentException('Idempotency key is too long.');
        }

        return self::hash($key);
    }

    public static function forBulkRecipient(?string $key, int $index, string $recipient): ?string
    {
        if ($key === null) {
            return null;
        }

        self::fromExternal($key);

        return self::hash("{$key}:{$index}:{$recipient}");
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
