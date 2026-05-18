<?php

namespace Infrastructure\Notifications\Delivery;

use Application\Notifications\Ports\NotificationDeliveryGateway;
use Domain\Notifications\Notification;
use Domain\Notifications\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Webmozart\Assert\Assert;

final class LogNotificationDeliveryGateway implements NotificationDeliveryGateway
{
    public function send(Notification $notification): void
    {
        match ($notification->channel) {
            NotificationChannel::Email => Assert::email(
                $notification->recipient,
                'Email recipient must be a valid email address.',
            ),
            NotificationChannel::Sms => Assert::regex(
                $notification->recipient,
                '/^\+[1-9]\d{7,14}$/',
                'SMS recipient must be an E.164 phone number.',
            ),
            NotificationChannel::Push => Assert::minLength(
                $notification->recipient,
                10,
                'Push recipient must be a device token.',
            ),
        };

        Log::info('Notification delivered.', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'recipient' => $notification->recipient,
        ]);
    }
}
