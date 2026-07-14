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
     *     data?: array<string, mixed>|null,
     *     channels?: array<int, string>|null,
     *     scheduled_at?: string|null
     * }
     */
    public function payload(): array
    {
        $payload = [
            'target' => $this->targetPayload(),
            'title' => $this->string('title')->toString(),
        ];

        if ($this->filled('body')) {
            $payload['body'] = $this->string('body')->toString();
        }

        if ($this->filled('data')) {
            $payload['data'] = $this->array('data');
        }

        if ($this->filled('channels')) {
            /** @var array<int, string> $channels */
            $channels = $this->array('channels');
            $payload['channels'] = $channels;
        }

        if ($this->filled('scheduled_at')) {
            $payload['scheduled_at'] = $this->string('scheduled_at')->toString();
        }

        return $payload;
    }
}
