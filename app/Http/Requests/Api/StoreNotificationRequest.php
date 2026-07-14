<?php

namespace App\Http\Requests\Api;

use App\Models\PushNotification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotificationRequest extends FormRequest
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
            'target' => ['required', 'array'],
            'target.type' => ['required', Rule::in([PushNotification::TARGET_USER, PushNotification::TARGET_GROUP])],
            'target.id' => ['nullable', 'integer'],
            'target.email' => ['nullable', 'email'],
            'target.slug' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['push', 'mail', 'sms'])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->input('target', []);
                $type = $target['type'] ?? null;

                $hasUserRef = isset($target['id']) || isset($target['email']);
                $hasGroupRef = isset($target['id']) || isset($target['slug']);

                if ($type === PushNotification::TARGET_USER && ! $hasUserRef) {
                    $validator->errors()->add('target', 'A user target requires "id" or "email".');
                }

                if ($type === PushNotification::TARGET_GROUP && ! $hasGroupRef) {
                    $validator->errors()->add('target', 'A group target requires "id" or "slug".');
                }
            },
        ];
    }
}
