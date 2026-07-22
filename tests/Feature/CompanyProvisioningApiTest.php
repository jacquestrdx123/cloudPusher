<?php

use App\Models\Company;

beforeEach(function (): void {
    config()->set('pushservice.sync.provisioning_key', 'test-provisioning-key');
});

it('creates a company for an upstream system and returns its hmac secret', function () {
    test()->postJson(route('api.v1.companies.store'), [
        'name' => 'Acme Corp',
        'default_channels' => ['push', 'mail'],
    ], [
        'Authorization' => 'Bearer test-provisioning-key',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Corp')
        ->assertJsonPath('data.slug', 'acme-corp')
        ->assertJsonPath('data.default_channels', ['push', 'mail'])
        ->assertJsonPath('created', true)
        ->assertJsonStructure(['data' => ['id', 'hmac_secret', 'slug']]);

    expect(Company::query()->where('slug', 'acme-corp')->exists())->toBeTrue();
});

it('is idempotent when the slug already exists', function () {
    $company = Company::factory()->create(['slug' => 'acme-corp', 'name' => 'Old Name']);

    test()->postJson(route('api.v1.companies.store'), [
        'name' => 'New Name',
        'slug' => 'acme-corp',
    ], [
        'Authorization' => 'Bearer test-provisioning-key',
    ])
        ->assertOk()
        ->assertJsonPath('created', false)
        ->assertJsonPath('data.id', $company->id)
        ->assertJsonPath('data.name', 'New Name');

    expect(Company::query()->count())->toBe(1);
});

it('rejects an invalid provisioning token', function () {
    test()->postJson(route('api.v1.companies.store'), [
        'name' => 'Acme Corp',
    ], [
        'Authorization' => 'Bearer wrong-key',
    ])->assertUnauthorized();

    expect(Company::query()->count())->toBe(0);
});

it('returns 404 when provisioning is disabled', function () {
    config()->set('pushservice.sync.provisioning_key', null);

    test()->postJson(route('api.v1.companies.store'), [
        'name' => 'Acme Corp',
    ], [
        'Authorization' => 'Bearer test-provisioning-key',
    ])->assertNotFound();
});

it('validates the company name', function () {
    test()->postJson(route('api.v1.companies.store'), [], [
        'Authorization' => 'Bearer test-provisioning-key',
    ])->assertUnprocessable()->assertJsonValidationErrorFor('name');
});
