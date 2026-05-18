<?php

namespace Tests\Unit;

use Domain\Notifications\Notification;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Illuminate\Support\Facades\Log;
use Infrastructure\Notifications\Delivery\LogNotificationDeliveryGateway;
use Infrastructure\Notifications\Identity\UuidNotificationIdGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LogNotificationDeliveryGatewayTest extends TestCase
{
    public function test_gateway_logs_valid_notification_delivery(): void
    {
        Log::shouldReceive('info')->once();

        (new LogNotificationDeliveryGateway())->send($this->notification(
            channel: NotificationChannel::Email,
            recipient: 'subscriber@example.com',
        ));
    }

    /**
     * @param  array{channel: NotificationChannel, recipient: string, error: string}  $case
     */
    #[DataProvider('invalidRecipients')]
    public function test_gateway_rejects_invalid_recipients(array $case): void
    {
        Log::shouldReceive('info')->never();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($case['error']);

        (new LogNotificationDeliveryGateway())->send($this->notification(
            channel: $case['channel'],
            recipient: $case['recipient'],
        ));
    }

    /**
     * @return iterable<string, array{array{channel: NotificationChannel, recipient: string, error: string}}>
     */
    public static function invalidRecipients(): iterable
    {
        yield 'email' => [[
            'channel' => NotificationChannel::Email,
            'recipient' => 'invalid',
            'error' => 'Email recipient must be a valid email address.',
        ]];

        yield 'sms' => [[
            'channel' => NotificationChannel::Sms,
            'recipient' => '12345',
            'error' => 'SMS recipient must be an E.164 phone number.',
        ]];

        yield 'push' => [[
            'channel' => NotificationChannel::Push,
            'recipient' => 'short',
            'error' => 'Push recipient must be a device token.',
        ]];
    }

    private function notification(NotificationChannel $channel, string $recipient): Notification
    {
        return Notification::queue(
            id: (new UuidNotificationIdGenerator())->generate(),
            idempotencyKey: null,
            subscriberId: $recipient,
            channel: $channel,
            priority: NotificationPriority::Marketing,
            recipient: $recipient,
            subject: null,
            body: 'Hello.',
            payload: null,
        );
    }
}
