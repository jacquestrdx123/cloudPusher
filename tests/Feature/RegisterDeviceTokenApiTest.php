<?php

use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @param  array<string, mixed>  $payload
 */
function registerDeviceToken(Company $company, array $payload, ?string $token = null): TestResponse
{
    return test()->postJson(
        route('api.v1.device-tokens.store', $company),
        $payload,
        ['Authorization' => 'Bearer '.($token ?? $company->hmac_secret)],
    );
}

it('rejects requests without a valid company token', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    registerDeviceToken($company, [
        'user' => ['id' => $user->id],
        'platform' => DeviceToken::PLATFORM_FCM,
        'token' => 'device-token-abc',
    ], token: 'wrong-token')->assertUnauthorized();
});

it('returns 404 for an inactive company', function () {
    $company = Company::factory()->inactive()->create();
    $user = User::factory()->forCompany($company)->create();

    registerDeviceToken($company, [
        'user' => ['id' => $user->id],
        'platform' => DeviceToken::PLATFORM_FCM,
        'token' => 'device-token-abc',
    ])->assertNotFound();
});

it('validates the device token payload', function () {
    $company = Company::factory()->create();

    registerDeviceToken($company, [
        'user' => [],
        'platform' => 'windows',
        'token' => '',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user', 'platform', 'token']);
});

it('registers a device token for a company user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();

    $response = registerDeviceToken($company, [
        'user' => ['email' => $user->email],
        'platform' => DeviceToken::PLATFORM_FCM,
        'token' => 'fcm-token-123',
        'name' => 'Pixel 8',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.platform', DeviceToken::PLATFORM_FCM)
        ->assertJsonPath('data.token', 'fcm-token-123')
        ->assertJsonPath('data.name', 'Pixel 8');

    expect(DeviceToken::query()->count())->toBe(1)
        ->and(DeviceToken::firstOrFail()->user_id)->toBe($user->id);
});

it('updates an existing token when the same platform and token are re-registered', function () {
    $company = Company::factory()->create();
    $firstUser = User::factory()->forCompany($company)->create();
    $secondUser = User::factory()->forCompany($company)->create();

    DeviceToken::factory()->fcm()->for($firstUser)->create([
        'token' => 'shared-token',
        'name' => 'Old phone',
    ]);

    registerDeviceToken($company, [
        'user' => ['id' => $secondUser->id],
        'platform' => DeviceToken::PLATFORM_FCM,
        'token' => 'shared-token',
        'name' => 'New phone',
    ])
        ->assertOk()
        ->assertJsonPath('data.user_id', $secondUser->id)
        ->assertJsonPath('data.name', 'New phone');

    expect(DeviceToken::query()->count())->toBe(1)
        ->and(DeviceToken::firstOrFail()->user_id)->toBe($secondUser->id);
});

it('rejects registering a token for a user from another company', function () {
    $company = Company::factory()->create();
    $otherUser = User::factory()->create();

    registerDeviceToken($company, [
        'user' => ['id' => $otherUser->id],
        'platform' => DeviceToken::PLATFORM_APNS,
        'token' => 'apns-token-123',
    ])->assertStatus(422);

    expect(DeviceToken::query()->count())->toBe(0);
});

it('deletes a device token that belongs to the company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $deviceToken = DeviceToken::factory()->for($user)->create();

    test()->deleteJson(
        route('api.v1.device-tokens.destroy', [$company, $deviceToken]),
        headers: ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNoContent();

    expect(DeviceToken::query()->find($deviceToken->id))->toBeNull();
});

it('does not delete a device token from another company', function () {
    $company = Company::factory()->create();
    $foreignToken = DeviceToken::factory()->create();

    test()->deleteJson(
        route('api.v1.device-tokens.destroy', [$company, $foreignToken]),
        headers: ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNotFound();

    expect(DeviceToken::query()->find($foreignToken->id))->not->toBeNull();
});
