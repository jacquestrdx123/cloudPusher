<?php

use App\Models\Company;
use App\Models\User;
use App\Models\UserGroup;

function syncCompany(Company $company, array $payload, ?string $token = null)
{
    return test()->putJson(route('api.v1.sync', $company), $payload, [
        'Authorization' => 'Bearer '.($token ?? $company->hmac_secret),
    ]);
}

it('creates and attaches users to the company', function () {
    $company = Company::factory()->create();

    syncCompany($company, [
        'users' => [
            ['external_id' => 'ext-1', 'name' => 'Jane Doe', 'email' => 'jane@acme.test', 'mobile_number' => '+27821234567', 'is_company_admin' => true],
            ['external_id' => 'ext-2', 'name' => 'John Roe', 'mobile_number' => '+27829876543'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('users.created', 2)
        ->assertJsonPath('users.updated', 0);

    $jane = User::query()->where('phone', '+27821234567')->first();

    expect($jane)->not->toBeNull();
    expect($jane->belongsToCompany($company))->toBeTrue();
    expect($jane->isCompanyAdminOf($company))->toBeTrue();
    expect($jane->email)->toBe('jane@acme.test');
    expect($company->users()->count())->toBe(2);
    expect($company->users()->wherePivot('external_id', 'ext-1')->exists())->toBeTrue();
});

it('is idempotent on re-sync', function () {
    $company = Company::factory()->create();

    $payload = ['users' => [
        ['external_id' => 'ext-1', 'name' => 'Jane Doe', 'mobile_number' => '+27821234567', 'email' => 'jane@acme.test'],
    ]];

    syncCompany($company, $payload)->assertOk();

    syncCompany($company, $payload)
        ->assertOk()
        ->assertJsonPath('users.created', 0)
        ->assertJsonPath('users.unchanged', 1);

    expect($company->users()->count())->toBe(1);
});

it('updates a matched user profile and matches by external id across mobile changes', function () {
    $company = Company::factory()->create();

    syncCompany($company, ['users' => [
        ['external_id' => 'ext-1', 'name' => 'Jane Doe', 'mobile_number' => '+27821234567', 'email' => 'jane@acme.test'],
    ]])->assertOk();

    syncCompany($company, ['users' => [
        ['external_id' => 'ext-1', 'name' => 'Jane Smith', 'mobile_number' => '+27829998877', 'email' => 'jane.smith@acme.test'],
    ]])
        ->assertOk()
        ->assertJsonPath('users.updated', 1);

    $user = $company->users()->wherePivot('external_id', 'ext-1')->first();

    expect($user->name)->toBe('Jane Smith');
    expect($user->phone)->toBe('+27829998877');
    expect($user->email)->toBe('jane.smith@acme.test');
    expect($company->users()->count())->toBe(1);
});

it('detaches members missing from the payload when delete_missing_users is set', function () {
    $company = Company::factory()->create();
    $keep = User::factory()->forCompany($company)->create(['phone' => '+27821111111', 'email' => 'keep@acme.test']);
    $drop = User::factory()->forCompany($company)->create(['phone' => '+27822222222', 'email' => 'drop@acme.test']);

    syncCompany($company, [
        'users' => [
            ['name' => 'Keep', 'mobile_number' => '+27821111111', 'email' => 'keep@acme.test'],
        ],
        'delete_missing_users' => true,
    ])
        ->assertOk()
        ->assertJsonPath('users.removed', 1);

    expect($keep->fresh()->belongsToCompany($company))->toBeTrue();
    expect($drop->fresh()->belongsToCompany($company))->toBeFalse();
    // Global user is preserved, only membership removed.
    expect(User::query()->whereKey($drop->id)->exists())->toBeTrue();
});

it('does not overwrite the global profile of a user owned by another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $shared = User::factory()->forCompany($companyA)->create([
        'name' => 'Original',
        'email' => 'shared@acme.test',
        'phone' => '+27821234567',
    ]);

    syncCompany($companyB, ['users' => [
        ['name' => 'Renamed', 'mobile_number' => '+27821234567', 'email' => 'renamed@acme.test'],
    ]])->assertOk();

    expect($shared->fresh()->name)->toBe('Original');
    expect($shared->fresh()->email)->toBe('shared@acme.test');
    expect($shared->fresh()->belongsToCompany($companyB))->toBeTrue();
});

it('associates an existing user by mobile_number instead of creating a duplicate', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $existing = User::factory()->forCompany($companyA)->create([
        'email' => 'shared@acme.test',
        'phone' => '+27821234567',
    ]);

    syncCompany($companyB, ['users' => [
        ['external_id' => 'upstream-1', 'name' => 'Should Not Apply', 'mobile_number' => '+27821234567', 'email' => 'other@acme.test'],
    ]])
        ->assertOk()
        ->assertJsonPath('users.created', 1);

    expect(User::query()->where('phone', '+27821234567')->count())->toBe(1)
        ->and($existing->fresh()->belongsToCompany($companyB))->toBeTrue()
        ->and($existing->fresh()->email)->toBe('shared@acme.test')
        ->and($companyB->users()->wherePivot('external_id', 'upstream-1')->first()?->is($existing))->toBeTrue();
});

it('accepts legacy phone as an alias for mobile_number', function () {
    $company = Company::factory()->create();

    syncCompany($company, ['users' => [
        ['name' => 'Jane', 'phone' => '+27821234567', 'email' => 'jane@acme.test'],
    ]])
        ->assertOk()
        ->assertJsonPath('users.created', 1);

    expect(User::query()->where('phone', '+27821234567')->exists())->toBeTrue();
});

it('syncs groups and their membership', function () {
    $company = Company::factory()->create();

    syncCompany($company, [
        'users' => [
            ['external_id' => 'u1', 'name' => 'Jane', 'mobile_number' => '+27821111111', 'email' => 'jane@acme.test'],
            ['external_id' => 'u2', 'name' => 'John', 'mobile_number' => '+27822222222', 'email' => 'john@acme.test'],
        ],
        'groups' => [
            [
                'external_id' => 'g1',
                'name' => 'Engineering',
                'members' => [
                    ['external_id' => 'u1'],
                    ['mobile_number' => '+27822222222'],
                ],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('groups.created', 1)
        ->assertJsonPath('groups.members_synced', 2);

    $group = UserGroup::query()->where('external_id', 'g1')->first();

    expect($group)->not->toBeNull();
    expect($group->company_id)->toBe($company->id);
    expect($group->users()->count())->toBe(2);
});

it('reports members that are not company users', function () {
    $company = Company::factory()->create();

    syncCompany($company, [
        'groups' => [
            ['external_id' => 'g1', 'name' => 'Engineering', 'members' => [['mobile_number' => '+27820000000']]],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('groups.created', 1)
        ->assertJsonPath('groups.members_synced', 0);

    $group = UserGroup::query()->where('external_id', 'g1')->first();

    expect($group->users()->count())->toBe(0);
});

it('replaces group membership authoritatively', function () {
    $company = Company::factory()->create();

    syncCompany($company, [
        'users' => [
            ['external_id' => 'u1', 'name' => 'Jane', 'mobile_number' => '+27821111111', 'email' => 'jane@acme.test'],
            ['external_id' => 'u2', 'name' => 'John', 'mobile_number' => '+27822222222', 'email' => 'john@acme.test'],
        ],
        'groups' => [
            ['external_id' => 'g1', 'name' => 'Engineering', 'members' => [['external_id' => 'u1'], ['external_id' => 'u2']]],
        ],
    ])->assertOk();

    syncCompany($company, [
        'groups' => [
            ['external_id' => 'g1', 'name' => 'Engineering', 'members' => [['external_id' => 'u2']]],
        ],
    ])->assertOk();

    $group = UserGroup::query()->where('external_id', 'g1')->first();

    expect($group->users()->count())->toBe(1);
    expect($group->users()->first()->email)->toBe('john@acme.test');
});

it('deletes groups missing from the payload when delete_missing_groups is set', function () {
    $company = Company::factory()->create();
    $keep = UserGroup::factory()->create(['company_id' => $company->id, 'slug' => 'keep', 'external_id' => 'keep']);
    $drop = UserGroup::factory()->create(['company_id' => $company->id, 'slug' => 'drop', 'external_id' => 'drop']);

    syncCompany($company, [
        'groups' => [
            ['external_id' => 'keep', 'name' => 'Keep'],
        ],
        'delete_missing_groups' => true,
    ])
        ->assertOk()
        ->assertJsonPath('groups.removed', 1);

    expect(UserGroup::query()->whereKey($keep->id)->exists())->toBeTrue();
    expect(UserGroup::query()->whereKey($drop->id)->exists())->toBeFalse();
});

it('rejects an invalid sync token', function () {
    $company = Company::factory()->create();

    syncCompany($company, ['users' => []], 'nope')->assertUnauthorized();
});

it('does not expose a company provisioning endpoint', function () {
    test()->postJson('/api/v1/companies', [
        'name' => 'Acme Corp',
    ])->assertNotFound();
});

it('validates that each user has a mobile_number', function () {
    $company = Company::factory()->create();

    syncCompany($company, ['users' => [
        ['name' => 'No Mobile', 'email' => 'nomobile@acme.test'],
    ]])->assertUnprocessable();
});
