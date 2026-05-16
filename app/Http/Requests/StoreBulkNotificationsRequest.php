<?php

namespace App\Http\Requests;

use Domain\Notifications\NotificationChannel;
use Domain\Notifications\NotificationPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkNotificationsRequest extends FormRequest
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
            'idempotency_key' => ['nullable', 'string', 'max:120'],
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
}
