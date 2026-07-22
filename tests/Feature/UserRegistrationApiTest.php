<?php

use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\UserApiToken;
use App\Models\UserRegistration;

it('registers a pending user for a company with a password', function () {
    $company = Company::factory()->create();

    test()->postJson(route('api.v1.auth.register', $company), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+27 82 123 4567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.phone', '+27821234567')
        ->assertJsonPath('data.company.slug', $company->slug)
        ->assertJsonMissingPath('data.password');

    expect(UserRegistration::query()->pending()->count())->toBe(1)
        ->and(User::query()->where('phone', '+27821234567')->exists())->toBeFalse();
});

it('tells the user their registration is still awaiting approval', function () {
    $company = Company::factory()->create();

    test()->postJson(route('api.v1.auth.register', $company), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+27821234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.phone.0',
            'Your account has not been approved yet. Please wait for a company admin to approve your registration.',
        );
});

it('tells the user when their registration was rejected', function () {
    $company = Company::factory()->create();

    UserRegistration::factory()->for($company)->rejected()->create([
        'phone' => '+27821234567',
        'password' => bcrypt('password'),
    ]);

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.phone.0',
            'Your registration was rejected. Please contact your company admin or register again.',
        );
});

it('allows a company admin to approve a registration so the user can log in with their password', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create(['phone' => '+27821111111']);
    $issued = UserApiToken::issue($admin, 'test');

    test()->postJson(route('api.v1.auth.register', $company), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+27821234567',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
    ])->assertCreated();

    $registration = UserRegistration::query()->where('phone', '+27821234567')->firstOrFail();

    test()->postJson(route('api.v1.registrations.approve', [$company, $registration]), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');

    expect($registration->fresh()->status)->toBe(UserRegistrationStatus::Approved)
        ->and(User::query()->where('phone', '+27821234567')->exists())->toBeTrue();

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'secret-pass',
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.email', 'jane@example.com');
});

it('associates an existing user when approving a registration instead of creating a duplicate', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $existing = User::factory()->forCompany($companyA)->create([
        'name' => 'Original Name',
        'email' => 'jane@example.com',
        'phone' => '+27821234567',
        'password' => 'existing-pass',
    ]);
    $admin = User::factory()->forCompany($companyB, true)->create(['phone' => '+27821111111']);
    $issued = UserApiToken::issue($admin, 'test');

    $registration = UserRegistration::factory()->for($companyB)->create([
        'name' => 'Jane From Registration',
        'email' => 'jane@example.com',
        'phone' => '+27821234567',
        'password' => bcrypt('registration-pass'),
    ]);

    test()->postJson(route('api.v1.registrations.approve', [$companyB, $registration]), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');

    expect(User::query()->where('email', 'jane@example.com')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Original Name')
        ->and($existing->fresh()->belongsToCompany($companyB))->toBeTrue()
        ->and($registration->fresh()->user_id)->toBe($existing->id);

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27821234567',
        'password' => 'existing-pass',
    ])->assertSuccessful();
});

it('forbids regular users from approving registrations', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    $issued = UserApiToken::issue($user, 'test');
    $registration = UserRegistration::factory()->for($company)->create();

    test()->postJson(route('api.v1.registrations.approve', [$company, $registration]), [], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])->assertForbidden();
});

it('allows a company admin to reject a registration', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $issued = UserApiToken::issue($admin, 'test');
    $registration = UserRegistration::factory()->for($company)->create();

    test()->postJson(route('api.v1.registrations.reject', [$company, $registration]), [
        'notes' => 'Not authorized for this company.',
    ], [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected');

    expect($registration->fresh()->status)->toBe(UserRegistrationStatus::Rejected)
        ->and(User::query()->where('phone', $registration->phone)->exists())->toBeFalse();
});

it('lists registrations for a company admin', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $issued = UserApiToken::issue($admin, 'test');

    UserRegistration::factory()->for($company)->count(2)->create();
    UserRegistration::factory()->create();

    test()->getJson(route('api.v1.registrations.index', $company).'?status=pending', [
        'Authorization' => 'Bearer '.$issued['plain_text_token'],
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
