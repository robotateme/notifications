<?php

namespace App\Http\Requests;

use Application\Notifications\Commands\CreateNotificationCommand;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:120'],
            'subscriber_id' => ['nullable', 'string', 'max:255'],
            'channel' => [
                'required',
                'string',
                Rule::in([
                    NotificationChannel::Email->value,
                    NotificationChannel::Sms->value,
                    NotificationChannel::Push->value,
                ]),
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in([
                    NotificationPriority::Transactional->value,
                    NotificationPriority::Marketing->value,
                ]),
            ],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'required_without:payload', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ];
    }

    public function toCommand(): CreateNotificationCommand
    {
        $recipient = (string) $this->validated('recipient');

        return new CreateNotificationCommand(
            idempotencyKey: $this->optionalString('idempotency_key'),
            subscriberId: $this->optionalString('subscriber_id') ?? $recipient,
            channel: NotificationChannel::from((string) $this->validated('channel')),
            priority: NotificationPriority::from((string) $this->validated('priority', NotificationPriority::Marketing->value)),
            recipient: $recipient,
            subject: $this->optionalString('subject'),
            body: $this->optionalString('body'),
            payload: $this->optionalArray('payload'),
        );
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key, null);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionalArray(string $key): ?array
    {
        $value = $this->validated($key, null);

        return is_array($value) ? $value : null;
    }
}
