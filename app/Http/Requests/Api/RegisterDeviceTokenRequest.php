<?php

namespace App\Http\Requests\Api;

use App\Models\DeviceToken;
use App\Models\User;
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
            'user' => ['nullable', 'array'],
            'user.id' => ['nullable', 'integer'],
            'user.email' => ['nullable', 'email'],
            'user.phone' => ['nullable', 'string', 'max:32'],
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
                if ($this->user() instanceof User) {
                    return;
                }

                $user = $this->input('user', []);

                if (! isset($user['id']) && ! isset($user['email']) && ! isset($user['phone'])) {
                    $validator->errors()->add('user', 'A user reference requires "id", "email", or "phone".');
                }
            },
        ];
    }

    /**
     * @return array{
     *     user: array{id?: int|null, email?: string|null, phone?: string|null},
     *     platform: string,
     *     token: string,
     *     name?: string|null
     * }
     */
    public function payload(): array
    {
        $authenticated = $this->user();

        if ($authenticated instanceof User) {
            $user = ['id' => $authenticated->id];
        } else {
            $user = [];

            if ($this->filled('user.id')) {
                $user['id'] = $this->integer('user.id');
            }

            if ($this->filled('user.email')) {
                $user['email'] = $this->string('user.email')->toString();
            }

            if ($this->filled('user.phone')) {
                $user['phone'] = $this->string('user.phone')->toString();
            }
        }

        $payload = [
            'user' => $user,
            'platform' => $this->string('platform')->toString(),
            'token' => $this->string('token')->toString(),
        ];

        if ($this->filled('name')) {
            $payload['name'] = $this->string('name')->toString();
        }

        return $payload;
    }
}
