<?php

declare(strict_types=1);

namespace Infrastructure\Notifications\Identity;

use Application\Notifications\Ports\NotificationIdGenerator;
use Domain\Notifications\NotificationId;
use Ramsey\Uuid\Uuid;

final class UuidNotificationIdGenerator implements NotificationIdGenerator
{
    public function generate(): string
    {
        return NotificationId::fromString(Uuid::uuid4()->toString())->value();
    }
}
