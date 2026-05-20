<?php

declare(strict_types=1);

namespace Domain\Notifications;

use JsonSerializable;

/**
 * Arbitrary provider/domain payload attached to a notification.
 *
 * The value is intentionally schema-less at Domain level because concrete
 * providers can require different metadata.
 */
final readonly class NotificationPayload implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $value
     */
    private function __construct(private array $value) {}

    /**
     * @param  array<string, mixed>|null  $value
     */
    public static function fromNullableArray(?array $value): ?self
    {
        return $value === null ? null : new self($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->value;
    }
}
