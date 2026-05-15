<?php

namespace App\Http\Requests;

use Domain\Notifications\NotificationChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
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
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'channel' => [
                'required',
                'string',
                Rule::in([
                    NotificationChannel::Email->value,
                    NotificationChannel::Sms->value,
                    NotificationChannel::Push->value,
                ]),
            ],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'required_without:payload', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
