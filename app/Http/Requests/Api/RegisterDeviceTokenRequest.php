<?php

namespace App\Http\Requests\Api;

use App\Models\DeviceToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterDeviceTokenRequest extends FormRequest
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
            'user' => ['required', 'array'],
            'user.id' => ['nullable', 'integer'],
            'user.email' => ['nullable', 'email'],
            'platform' => ['required', Rule::in([DeviceToken::PLATFORM_FCM, DeviceToken::PLATFORM_APNS])],
            'token' => ['required', 'string', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->input('user', []);

                if (! isset($user['id']) && ! isset($user['email'])) {
                    $validator->errors()->add('user', 'A user reference requires "id" or "email".');
                }
            },
        ];
    }
}
