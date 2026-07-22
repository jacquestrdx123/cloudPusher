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
            function (Validator $validator): void {
                $this->validateNotificationTarget($validator);
            },
        ];
    }

    /**
     * @return array{
     *     target: array{type: string, id?: int|null, email?: string|null, slug?: string|null},
     *     title: string,
     *     body?: string|null,
     *     image_url?: string|null,
     *     sound?: string|null,
     *     category?: string|null,
     *     android_channel_id?: string|null,
     *     data?: array<string, mixed>|null,
     *     channels?: array<int, string>|null,
     *     scheduled_at?: string|null
     * }
     */
    public function payload(): array
    {
        return $this->notificationPayload();
    }
}
