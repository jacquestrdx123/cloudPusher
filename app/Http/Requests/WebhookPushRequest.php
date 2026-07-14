<?php

namespace App\Http\Requests;

use App\Models\PushNotification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookPushRequest extends FormRequest
{
    /**
     * The signature middleware already authenticated the caller.
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
            'target' => ['required', 'array'],
            'target.type' => ['required', Rule::in([PushNotification::TARGET_USER, PushNotification::TARGET_GROUP])],
            'target.id' => ['nullable', 'integer'],
            'target.email' => ['nullable', 'email'],
            'target.slug' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['push', 'mail', 'sms'])],
        ];
    }

    protected function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
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
        });
    }
}
