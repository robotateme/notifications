<?php

namespace App\Http\Requests;

use Domain\Notifications\NotificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmDeliveryRequest extends FormRequest
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
}
