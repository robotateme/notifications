<?php

namespace App\Http\Requests;

use Application\Notifications\Commands\CreateBulkNotificationsCommand;
use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreBulkNotificationsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('body') && $this->has('message')) {
            $this->merge(['body' => $this->input('message')]);
        }
    }

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
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:120'],
            'channel' => [
                'required',
                'string',
                Rule::in([
                    NotificationChannel::Email->value,
                    NotificationChannel::Sms->value,
                ]),
            ],
            'priority' => [
                'required',
                'string',
                Rule::in([
                    NotificationPriority::Transactional->value,
                    NotificationPriority::Marketing->value,
                ]),
            ],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'recipients' => ['required', 'array', 'min:1', 'max:1000'],
            'recipients.*' => ['required', 'string', 'distinct', 'max:255'],
        ];
    }

    public function toCommand(): CreateBulkNotificationsCommand
    {
        /** @var array<int, string> $recipients */
        $recipients = array_values($this->validated('recipients'));

        return new CreateBulkNotificationsCommand(
            channel: NotificationChannel::from((string) $this->validated('channel')),
            priority: NotificationPriority::from((string) $this->validated('priority')),
            body: (string) $this->validated('body'),
            recipients: $recipients,
            subject: $this->optionalString('subject'),
            idempotencyKey: $this->optionalString('idempotency_key'),
            traceId: $this->traceId(),
        );
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key, null);

        return is_string($value) ? $value : null;
    }

    private function traceId(): string
    {
        $traceId = $this->header('X-Trace-Id') ?: $this->header('X-Request-Id');

        if (is_string($traceId) && $traceId !== '' && strlen($traceId) <= 128) {
            return $traceId;
        }

        return (string) Str::uuid();
    }
}
