<?php

namespace Infrastructure\Notifications\Delivery;

use Application\Notifications\Ports\NotificationDeliveryGateway;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LogNotificationDeliveryGateway implements NotificationDeliveryGateway
{
    public function send(Notification $notification): void
    {
        match ($notification->channel) {
            NotificationChannel::Email => $this->assertEmail($notification->recipient),
            NotificationChannel::Sms => $this->assertPhone($notification->recipient),
            NotificationChannel::Push => $this->assertDeviceToken($notification->recipient),
        };

        Log::info('Notification delivered.', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'recipient' => $notification->recipient,
        ]);
    }

    private function assertEmail(string $recipient): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email recipient must be a valid email address.');
        }
    }

    private function assertPhone(string $recipient): void
    {
        if (! preg_match('/^\+[1-9]\d{7,14}$/', $recipient)) {
            throw new InvalidArgumentException('SMS recipient must be an E.164 phone number.');
        }
    }

    private function assertDeviceToken(string $recipient): void
    {
        if (strlen($recipient) < 10) {
            throw new InvalidArgumentException('Push recipient must be a device token.');
        }
    }
}
