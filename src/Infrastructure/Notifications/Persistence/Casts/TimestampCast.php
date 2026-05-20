<?php

declare(strict_types=1);

namespace Infrastructure\Notifications\Persistence\Casts;

use DateTimeInterface;
use Domain\Shared\Timestamp;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Timestamp|null, DateTimeInterface|Timestamp|string|null>
 */
final class TimestampCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Timestamp
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Timestamp) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Timestamp::fromDateTime($value);
        }

        if (is_string($value)) {
            return Timestamp::fromString($value);
        }

        throw new InvalidArgumentException("Cannot cast [$key] to Timestamp.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Timestamp) {
            return $value->toDatabaseString();
        }

        if ($value instanceof DateTimeInterface) {
            return Timestamp::fromDateTime($value)->toDatabaseString();
        }

        if (is_string($value)) {
            return Timestamp::fromString($value)->toDatabaseString();
        }

        throw new InvalidArgumentException("Cannot cast [$key] from Timestamp.");
    }
}
