<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;

readonly class CreateNotificationCommand
{
    public function __construct(
        public ?string $idempotencyKey,
        public string $subscriberId,
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $recipient,
        public ?string $subject,
        public ?string $body,
        public ?array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idempotencyKey: $data['idempotency_key'] ?? null,
            subscriberId: $data['subscriber_id'] ?? $data['recipient'],
            channel: NotificationChannel::from($data['channel']),
            priority: NotificationPriority::from($data['priority'] ?? NotificationPriority::Marketing->value),
            recipient: $data['recipient'],
            subject: $data['subject'] ?? null,
            body: $data['body'] ?? null,
            payload: $data['payload'] ?? null,
        );
    }
}
