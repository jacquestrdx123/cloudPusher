<?php

use App\Console\Commands\SetupAppReviewDemo;
use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRegistration;

function runAppReviewDemo(string $phoneOne = '+27600000001', string $phoneTwo = '+27600000002')
{
    return test()->artisan('app-review:demo', ['phoneOne' => $phoneOne, 'phoneTwo' => $phoneTwo])
        ->expectsQuestion('Password for both demo accounts', 'review-pass-123');
}

it('creates the demo company with two accounts that can sign in', function () {
    runAppReviewDemo()->assertSuccessful();

    $company = Company::where('slug', SetupAppReviewDemo::COMPANY_SLUG)->firstOrFail();
    $reviewerOne = User::where('phone', '+27600000001')->firstOrFail();
    $reviewerTwo = User::where('phone', '+27600000002')->firstOrFail();

    expect($company->is_active)->toBeTrue()
        ->and($company->users()->count())->toBe(2)
        ->and($reviewerOne->isCompanyAdminOf($company))->toBeTrue()
        ->and($reviewerTwo->isCompanyAdminOf($company))->toBeFalse();

    test()->postJson(route('api.v1.auth.login'), [
        'phone' => '+27600000001',
        'password' => 'review-pass-123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('user.id', $reviewerOne->id);
});

it('seeds a sample inbox and a pending registration for the demo company', function () {
    runAppReviewDemo()->assertSuccessful();

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

    runAppReviewDemo()->assertSuccessful();
    runAppReviewDemo()->assertSuccessful();

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
