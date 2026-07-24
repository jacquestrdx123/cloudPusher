<?php

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Queue;

it('rejects notifications without a valid company token', function () {
    $company = Company::factory()->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => 1],
        'title' => 'Hi',
    ], token: 'wrong-token')->assertUnauthorized();
});

it('validates the notification payload', function () {
    $company = Company::factory()->create();

    storeNotification($company, [
        'target' => ['type' => 'user'],
        'title' => 'Hi',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');

    storeNotification($company, [
        'target' => ['type' => 'group', 'slug' => 'ops'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('queues a notification for a user', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Server down',
        'body' => 'Investigate now',
        'data' => ['severity' => 'critical'],
        'channels' => ['push', 'mail'],
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', PushNotification::STATUS_PENDING)
        ->assertJsonPath('data.target_type', PushNotification::TARGET_USER)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.title', 'Server down')
        ->assertJsonPath('data.body', 'Investigate now')
        ->assertJsonPath('data.payload.severity', 'critical')
        ->assertJsonPath('data.channels', ['push', 'mail']);

    Queue::assertPushed(ProcessPushNotification::class);
});

it('queues a notification for a user group by slug', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $group = UserGroup::factory()->for($company)->create(['slug' => 'ops']);
    $users = User::factory()->forCompany($company)->count(2)->create();
    $group->users()->attach($users);

    storeNotification($company, [
        'target' => ['type' => 'group', 'slug' => 'ops'],
        'title' => 'Ops alert',
        'body' => 'Check dashboards',
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.target_type', PushNotification::TARGET_GROUP)
        ->assertJsonPath('data.user_group_id', $group->id);

    Queue::assertPushed(ProcessPushNotification::class);
});

it('resolves a user target by email', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create(['email' => 'ops@acme.test']);

    storeNotification($company, [
        'target' => ['type' => 'user', 'email' => 'ops@acme.test'],
        'title' => 'Hello',
    ])->assertStatus(202);

    expect(PushNotification::firstOrFail()->user_id)->toBe($user->id);
});

it('returns 422 when the target does not belong to the company', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $otherUser = User::factory()->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $otherUser->id],
        'title' => 'Hello',
    ])->assertStatus(422);

    Queue::assertNothingPushed();
});

it('falls back to company default channels when none are supplied', function () {
    Queue::fake();

    $company = Company::factory()->create(['default_channels' => ['mail', 'sms']]);
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Hello',
    ])->assertStatus(202);

    expect(PushNotification::firstOrFail()->channels)->toBe(['mail', 'sms']);
});

it('persists data.url and data.url_label on the notification payload', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Power Failure',
        'body' => 'Device is down',
        'data' => [
            'url' => 'https://wispmon.example.com/device/1123',
            'url_label' => 'View device',
            'type' => 'power_down',
        ],
        'channels' => ['push'],
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.payload.url', 'https://wispmon.example.com/device/1123')
        ->assertJsonPath('data.payload.url_label', 'View device')
        ->assertJsonPath('data.payload.type', 'power_down');
});

it('rejects a non-https data.url', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Hello',
        'data' => [
            'url' => 'http://insecure.example.com/device/1',
            'url_label' => 'View device',
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('data.url');
});
