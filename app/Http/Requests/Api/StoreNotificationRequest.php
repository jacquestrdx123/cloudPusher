<?php

namespace App\Http\Requests\Api;

use App\Http\Concerns\ValidatesNotificationPayload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNotificationRequest extends FormRequest
{
    use ValidatesNotificationPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->notificationPayloadRules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator): mixed => $this->validateNotificationTarget($validator),
        ];
    }
}
