<?php

declare(strict_types=1);

namespace Domain\Notifications;

use Webmozart\Assert\Assert;

/**
 * Public notification identifier.
 *
 * Wraps a UUID string so Domain code cannot accidentally accept arbitrary ids.
 */
final readonly class NotificationId
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        Assert::uuid($value, 'Notification id must be a valid UUID.');

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
