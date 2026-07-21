<?php

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Notifications\WebhookPushNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('queues a broadcast notification to all company users', function () {
    Queue::fake();

    $company = Company::factory()->create();
    User::factory()->forCompany($company)->count(3)->create();
    User::factory()->create();

    test()->postJson(
        route('api.v1.notifications.store', $company),
        [
            'target' => ['type' => 'broadcast'],
            'title' => 'Company-wide alert',
            'body' => 'Please read',
            'channels' => ['mail'],
        ],
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertStatus(202)
        ->assertJsonPath('data.target_type', PushNotification::TARGET_BROADCAST)
        ->assertJsonPath('data.status', PushNotification::STATUS_PENDING);

    Queue::assertPushed(ProcessPushNotification::class);

    expect(PushNotification::firstOrFail())
        ->target_type->toBe(PushNotification::TARGET_BROADCAST)
        ->user_id->toBeNull()
        ->user_group_id->toBeNull();
});

it('fans out a broadcast to every user in the company', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $users = User::factory()->forCompany($company)->count(3)->create();
    User::factory()->create();

    $notification = PushNotification::factory()->create([
        'company_id' => $company->id,
        'target_type' => PushNotification::TARGET_BROADCAST,
        'channels' => ['mail'],
    ]);

    (new ProcessPushNotification($notification))->handle();

    Notification::assertSentTo($users, WebhookPushNotification::class);
    expect($notification->fresh()->recipients_count)->toBe(3);
});
