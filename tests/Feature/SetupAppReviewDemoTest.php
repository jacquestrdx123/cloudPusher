<?php

use App\Console\Commands\SetupAppReviewDemo;
use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRegistration;

/**
 * Run the command and return the password it generated.
 */
function runAppReviewDemo(string $phoneOne = '+27600000001', string $phoneTwo = '+27600000002'): string
{
    $exitCode = Artisan::call('app-review:demo', ['phoneOne' => $phoneOne, 'phoneTwo' => $phoneTwo]);

    expect($exitCode)->toBe(0);

    preg_match('/Password \(both accounts\)[ .]+([A-Za-z0-9]{16})\s/', Artisan::output(), $matches);

    return $matches[1] ?? '';
}

it('creates the demo company with two accounts that can sign in', function () {
    $password = runAppReviewDemo();

    $company = Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->firstOrFail();
    $reviewerOne = User::where('phone', '+27600000001')->firstOrFail();
    $reviewerTwo = User::where('phone', '+27600000002')->firstOrFail();

    expect($company->is_active)->toBeTrue()
        ->and($company->users()->count())->toBe(2)
        ->and($reviewerOne->isCompanyAdminOf($company))->toBeTrue()
        ->and($reviewerTwo->isCompanyAdminOf($company))->toBeFalse();

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27600000001',
        'password' => $password,
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.id', $reviewerOne->id);
});

it('generates a new random password on every run', function () {
    $first = runAppReviewDemo();
    $second = runAppReviewDemo();

    expect($first)->toHaveLength(16)
        ->and($second)->toHaveLength(16)
        ->and($second)->not->toBe($first);

    test()->postJson(route('api.v1.auth.login'), ['phone' => '+27600000002', 'password' => $first])
        ->assertUnprocessable();
    test()->postJson(route('api.v1.auth.login'), ['phone' => '+27600000002', 'password' => $second])
        ->assertSuccessful();
});

it('removes members a previous review run approved, but keeps users of other companies', function () {
    runAppReviewDemo();

    $company = Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->firstOrFail();
    $otherCompany = Company::factory()->create();

    $approved = User::factory()->forCompany($company)->create(['phone' => SetupAppReviewDemo::APPLICANT_PHONE]);
    $shared = User::factory()->forCompany($otherCompany)->create();
    $company->users()->attach($shared->id, ['is_company_admin' => false]);

    runAppReviewDemo();

    expect(User::whereKey($approved->id)->exists())->toBeFalse()
        ->and($company->users()->count())->toBe(2)
        ->and($shared->fresh()->belongsToCompany($otherCompany))->toBeTrue()
        ->and($shared->fresh()->belongsToCompany($company))->toBeFalse()
        ->and(UserRegistration::where('company_id', $company->id)
            ->where('status', UserRegistrationStatus::Pending)
            ->count())->toBe(1);
});

it('seeds a sample inbox and a pending registration for the demo company', function () {
    runAppReviewDemo();

    $company = Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->firstOrFail();
    $reviewerOne = User::where('phone', '+27600000001')->firstOrFail();

    expect(PushNotification::where('company_id', $company->id)->count())->toBe(5)
        ->and(UserNotification::where('user_id', $reviewerOne->id)->count())->toBe(5)
        ->and(UserNotification::where('user_id', $reviewerOne->id)->whereNull('read_at')->count())->toBe(3)
        ->and(UserRegistration::where('company_id', $company->id)
            ->where('status', UserRegistrationStatus::Pending)
            ->count())->toBe(1);
});

it('can be run again without duplicating data or touching other companies', function () {
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->forCompany($otherCompany)->create();
    UserNotification::factory()->forUser($otherUser)->create();

    runAppReviewDemo();
    runAppReviewDemo();

    $company = Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->firstOrFail();

    expect(Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->count())->toBe(1)
        ->and($company->users()->count())->toBe(2)
        ->and(PushNotification::where('company_id', $company->id)->count())->toBe(5)
        ->and(UserRegistration::where('company_id', $company->id)->count())->toBe(1)
        ->and(UserNotification::where('user_id', $otherUser->id)->count())->toBe(1);
});

it('refuses a mobile number that belongs to a user of another company', function () {
    $otherCompany = Company::factory()->create();
    $realUser = User::factory()->forCompany($otherCompany)->create([
        'phone' => '+27821234567',
        'password' => 'original-password',
    ]);

    test()->artisan('app-review:demo', ['phoneOne' => '+27821234567', 'phoneTwo' => '+27600000002'])
        ->assertFailed();

    expect(Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->exists())->toBeFalse()
        ->and($realUser->fresh()->belongsToCompany($otherCompany))->toBeTrue()
        ->and(Hash::check('original-password', $realUser->fresh()->password))->toBeTrue();
});

it('refuses two identical mobile numbers', function () {
    test()->artisan('app-review:demo', ['phoneOne' => '+27600000001', 'phoneTwo' => '+27 60 000 0001'])
        ->assertFailed();

    expect(User::count())->toBe(0);
});
