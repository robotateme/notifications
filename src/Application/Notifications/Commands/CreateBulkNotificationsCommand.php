<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

readonly class CreateBulkNotificationsCommand
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $body,
        public array $recipients,
        public ?string $subject = null,
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            channel: NotificationChannel::from($data['channel']),
            priority: NotificationPriority::from($data['priority'] ?? NotificationPriority::Marketing->value),
            body: $data['body'],
            recipients: $data['recipients'],
            subject: $data['subject'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
        );
    }
}
