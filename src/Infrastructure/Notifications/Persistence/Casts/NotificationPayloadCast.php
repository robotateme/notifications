<?php

namespace Infrastructure\Notifications\Persistence\Casts;

use Domain\Notifications\NotificationPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<NotificationPayload|null, array<string, mixed>|NotificationPayload|null>
 */
final class NotificationPayloadCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?NotificationPayload
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return NotificationPayload::fromNullableArray($value);
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? NotificationPayload::fromNullableArray($decoded) : null;
    }

    /**
     * @param  array<string, mixed>|NotificationPayload|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof NotificationPayload) {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
