<?php

namespace App\Http\Concerns;

use App\Models\PushNotification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesNotificationPayload
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function notificationPayloadRules(): array
    {
        return [
            'target' => ['required', 'array'],
            'target.type' => ['required', Rule::in([
                PushNotification::TARGET_USER,
                PushNotification::TARGET_GROUP,
                PushNotification::TARGET_BROADCAST,
            ])],
            'target.id' => ['nullable', 'integer'],
            'target.email' => ['nullable', 'email'],
            'target.slug' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'data' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['push', 'mail', 'sms'])],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function validateNotificationTarget(Validator $validator): void
    {
        $target = $this->input('target', []);
        $type = $target['type'] ?? null;

        if ($type === PushNotification::TARGET_BROADCAST) {
            return;
        }

        $hasUserRef = isset($target['id']) || isset($target['email']);
        $hasGroupRef = isset($target['id']) || isset($target['slug']);

        if ($type === PushNotification::TARGET_USER && ! $hasUserRef) {
            $validator->errors()->add('target', 'A user target requires "id" or "email".');
        }

        if ($type === PushNotification::TARGET_GROUP && ! $hasGroupRef) {
            $validator->errors()->add('target', 'A group target requires "id" or "slug".');
        }
    }

    /**
     * @return array{type: string, id?: int|null, email?: string|null, slug?: string|null}
     */
    protected function targetPayload(): array
    {
        $target = [
            'type' => $this->string('target.type')->toString(),
        ];

        if ($this->filled('target.id')) {
            $target['id'] = $this->integer('target.id');
        }

        if ($this->filled('target.email')) {
            $target['email'] = $this->string('target.email')->toString();
        }

        if ($this->filled('target.slug')) {
            $target['slug'] = $this->string('target.slug')->toString();
        }

        return $target;
    }
}
