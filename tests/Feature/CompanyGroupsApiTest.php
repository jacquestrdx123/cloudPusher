<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\User;
use App\Models\UserGroup;

it('lists the company groups with member counts', function () {
    $company = Company::factory()->create();
    $member = User::factory()->forCompany($company)->create();
    $colleague = User::factory()->forCompany($company)->create();

    $drivers = UserGroup::factory()->for($company)->create(['name' => 'Drivers']);
    $drivers->users()->attach([$member->id, $colleague->id]);
    UserGroup::factory()->for($company)->create(['name' => 'Admins']);
    UserGroup::factory()->create(['name' => 'Other company group']);

    $token = app(IssueUserApiToken::class)->handle($member)['plain_text_token'];

    test()->getJson(route('api.v1.groups.index', $company), [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Admins')
        ->assertJsonPath('data.0.users_count', 0)
        ->assertJsonPath('data.1.id', $drivers->id)
        ->assertJsonPath('data.1.users_count', 2);
});

it('rejects users who are not members of the company', function () {
    $company = Company::factory()->create();
    $outsider = User::factory()->forCompany(Company::factory())->create();
    $token = app(IssueUserApiToken::class)->handle($outsider)['plain_text_token'];

    test()->getJson(route('api.v1.groups.index', $company), [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnauthorized();
});
