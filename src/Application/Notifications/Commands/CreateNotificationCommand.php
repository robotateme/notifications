<?php

namespace Application\Notifications\Commands;

use Domain\Notifications\NotificationChannel;

readonly class CreateNotificationCommand
{
    public function __construct(
        public ?string $idempotencyKey,
        public NotificationChannel $channel,
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
            channel: NotificationChannel::from($data['channel']),
            recipient: $data['recipient'],
            subject: $data['subject'] ?? null,
            body: $data['body'] ?? null,
            payload: $data['payload'] ?? null,
        );
    }
}
