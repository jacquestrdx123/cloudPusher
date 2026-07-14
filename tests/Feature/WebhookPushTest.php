<?php

use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Build a signed webhook request and dispatch it.
 *
 * @param  array<string, mixed>  $payload
 */
function signedPush(Company $company, array $payload, ?string $secret = null): Illuminate\Testing\TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret ?? $company->hmac_secret);

    return test()->call(
        'POST',
        route('webhooks.push', $company),
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => "sha256={$signature}"],
        $body,
    );
}

it('rejects a request with a missing or invalid signature', function () {
    $company = Company::factory()->create();

    signedPush($company, ['target' => ['type' => 'user', 'id' => 1], 'title' => 'Hi'], secret: 'wrong-secret')
        ->assertStatus(401);
});

it('returns 404 for an unknown company', function () {
    $response = test()->call(
        'POST',
        '/api/webhooks/does-not-exist/push',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'sha256=abc'],
        json_encode(['title' => 'Hi']),
    );

    $response->assertStatus(404);
});

it('returns 404 for an inactive company', function () {
    $company = Company::factory()->inactive()->create();

    signedPush($company, ['target' => ['type' => 'user', 'id' => 1], 'title' => 'Hi'])
        ->assertStatus(404);
});

it('validates the payload', function () {
    $company = Company::factory()->create();

    signedPush($company, ['target' => ['type' => 'user'], 'title' => 'Hi'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');

    signedPush($company, ['target' => ['type' => 'user', 'id' => 1]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('accepts a signed webhook targeting a user and queues the job', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $response = signedPush($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Server down',
        'body' => 'Investigate now',
        'channels' => ['mail'],
    ]);

    $response->assertStatus(202)->assertJsonPath('data.status', PushNotification::STATUS_PENDING);

    $notification = PushNotification::firstOrFail();
    expect($notification->company_id)->toBe($company->id)
        ->and($notification->user_id)->toBe($user->id)
        ->and($notification->target_type)->toBe(PushNotification::TARGET_USER)
        ->and($notification->channels)->toBe(['mail']);

    Queue::assertPushed(ProcessPushNotification::class);
});

it('resolves a user target by email', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['email' => 'ops@acme.test']);

    signedPush($company, [
        'target' => ['type' => 'user', 'email' => 'ops@acme.test'],
        'title' => 'Hi',
    ])->assertStatus(202);

    expect(PushNotification::firstOrFail()->user_id)->toBe($user->id);
});

it('returns 422 when the target does not belong to the company', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $otherUser = User::factory()->create(); // different company

    signedPush($company, [
        'target' => ['type' => 'user', 'id' => $otherUser->id],
        'title' => 'Hi',
    ])->assertStatus(422);

    Queue::assertNothingPushed();
});

it('falls back to the company default channels when none are supplied', function () {
    Queue::fake();

    $company = Company::factory()->create(['default_channels' => ['mail', 'sms']]);
    $user = User::factory()->for($company)->create();

    signedPush($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Hi',
    ])->assertStatus(202);

    expect(PushNotification::firstOrFail()->channels)->toBe(['mail', 'sms']);
});
