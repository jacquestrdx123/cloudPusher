<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\User;

function memberToken(User $user): string
{
    return app(IssueUserApiToken::class)->handle($user)['plain_text_token'];
}

it('lets any company member list the company members', function () {
    $company = Company::factory()->create();
    $member = User::factory()->forCompany($company)->create(['name' => 'Bongani']);
    $colleague = User::factory()->forCompany($company, true)->create(['name' => 'Anele']);
    User::factory()->forCompany(Company::factory())->create(['name' => 'Outsider']);

    test()->getJson(route('api.v1.members.index', $company), [
        'Authorization' => 'Bearer '.memberToken($member),
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $colleague->id)
        ->assertJsonPath('data.0.is_company_admin', true)
        ->assertJsonPath('data.1.id', $member->id)
        ->assertJsonPath('data.1.company.slug', $company->slug)
        ->assertJsonPath('meta.total', 2);
});

it('filters members by name, email, or phone', function () {
    $company = Company::factory()->create();
    $member = User::factory()->forCompany($company)->create(['name' => 'Bongani']);
    $thandi = User::factory()->forCompany($company)->create([
        'name' => 'Thandi Mokoena',
        'phone' => '+27825550001',
    ]);
    User::factory()->forCompany($company)->create(['name' => 'Pieter']);

    $token = memberToken($member);

    test()->getJson(route('api.v1.members.index', [$company, 'search' => 'thandi']), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $thandi->id);

    test()->getJson(route('api.v1.members.index', [$company, 'search' => '5550001']), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $thandi->id);
});

it('rejects users who are not members of the company', function () {
    $company = Company::factory()->create();
    $outsider = User::factory()->forCompany(Company::factory())->create();

    test()->getJson(route('api.v1.members.index', $company), [
        'Authorization' => 'Bearer '.memberToken($outsider),
    ])->assertUnauthorized();
});

it('requires a user token to list members', function () {
    $company = Company::factory()->create();

    test()->getJson(route('api.v1.members.index', $company))->assertUnauthorized();
});
