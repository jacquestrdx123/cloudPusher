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
            'image_url' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'sound' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:64'],
            'android_channel_id' => ['nullable', 'string', 'max:64'],
            'data' => ['nullable', 'array'],
            'data.url' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'data.url_label' => ['nullable', 'string', 'max:100'],
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
    protected function notificationPayload(): array
    {
        $payload = [
            'target' => $this->targetPayload(),
            'title' => $this->string('title')->toString(),
        ];

        if ($this->filled('body')) {
            $payload['body'] = $this->string('body')->toString();
        }

        if ($this->filled('image_url')) {
            $payload['image_url'] = $this->string('image_url')->toString();
        }

        if ($this->filled('sound')) {
            $payload['sound'] = $this->string('sound')->toString();
        }

        if ($this->filled('category')) {
            $payload['category'] = $this->string('category')->toString();
        }

        if ($this->filled('android_channel_id')) {
            $payload['android_channel_id'] = $this->string('android_channel_id')->toString();
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
