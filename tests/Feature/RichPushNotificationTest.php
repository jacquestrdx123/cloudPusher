<?php

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('persists rich media fields from the notifications api', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Flash sale',
        'body' => 'Tap for deals',
        'image_url' => 'https://cdn.example.com/flash.jpg',
        'sound' => 'chime.caf',
        'category' => 'RICH_MESSAGE',
        'android_channel_id' => 'rich_messages_v1',
        'channels' => ['push'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.image_url', 'https://cdn.example.com/flash.jpg')
        ->assertJsonPath('data.sound', 'chime.caf')
        ->assertJsonPath('data.category', 'RICH_MESSAGE')
        ->assertJsonPath('data.android_channel_id', 'rich_messages_v1');

    $notification = PushNotification::query()->firstOrFail();

    expect($notification->image_url)->toBe('https://cdn.example.com/flash.jpg')
        ->and($notification->sound)->toBe('chime.caf')
        ->and($notification->category)->toBe('RICH_MESSAGE')
        ->and($notification->android_channel_id)->toBe('rich_messages_v1');

    Queue::assertPushed(ProcessPushNotification::class);
});

it('rejects a non-https image_url on the notifications api', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Bad image',
        'image_url' => 'http://cdn.example.com/insecure.jpg',
        'channels' => ['push'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_url');
});

it('persists rich media fields from the signed webhook', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    $payload = [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Webhook rich',
        'image_url' => 'https://cdn.example.com/webhook.jpg',
        'channels' => ['mail'],
    ];

    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $company->hmac_secret);

    $this->call(
        'POST',
        route('webhooks.push', $company),
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => "sha256={$signature}"],
        $body,
    )
        ->assertStatus(202)
        ->assertJsonPath('data.image_url', 'https://cdn.example.com/webhook.jpg');

    expect(PushNotification::query()->firstOrFail()->image_url)
        ->toBe('https://cdn.example.com/webhook.jpg');
});
