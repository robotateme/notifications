<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Application\Notifications\Commands\ConfirmNotificationDeliveryCommand;
use Domain\Notifications\NotificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    NotificationStatus::Delivered->value,
                    NotificationStatus::Dropped->value,
                ]),
            ],
            'error' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toCommand(string $notificationId): ConfirmNotificationDeliveryCommand
    {
        return new ConfirmNotificationDeliveryCommand(
            notificationId: $notificationId,
            status: NotificationStatus::from((string) $this->validated('status')),
            error: $this->optionalString('error'),
        );
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key, null);

        return is_string($value) ? $value : null;
    }
}
