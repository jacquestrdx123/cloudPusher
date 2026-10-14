<?php

use App\Actions\IssueUserApiToken;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->company = Company::factory()->create(['name' => 'Acme Logistics']);
    $this->admin = User::factory()->forCompany($this->company, true)->create(['name' => 'Anele']);
    $this->headers = [
        'Authorization' => 'Bearer '.app(IssueUserApiToken::class)->handle($this->admin)['plain_text_token'],
    ];
});

it('creates, attaches, and invites a brand-new person', function () {
    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'name' => 'Thandi Mokoena',
        'email' => 'Thandi@Example.com',
        'phone' => '+27 82 555 0001',
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.member.name', 'Thandi Mokoena')
        ->assertJsonPath('data.member.phone', '+27825550001')
        ->assertJsonPath('data.member.email', 'thandi@example.com')
        ->assertJsonPath('data.member.is_company_admin', false)
        ->assertJsonPath('data.member.company.slug', $this->company->slug);

    $user = User::where('phone', '+27825550001')->sole();
    $invitation = CompanyInvitation::sole();

    expect($user->belongsToCompany($this->company))->toBeTrue()
        ->and($invitation->user_id)->toBe($user->id)
        ->and($invitation->company_id)->toBe($this->company->id)
        ->and($invitation->invited_by_user_id)->toBe($this->admin->id)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentTo(
        $user,
        CompanyInvitationNotification::class,
        fn (CompanyInvitationNotification $notification, array $channels): bool => $channels === ['mail']
            && $notification->companyName === 'Acme Logistics'
            && $notification->inviterName === 'Anele'
            && str_contains($notification->acceptUrl, '/invite/'),
    );
});

it('can make the new person a company admin', function () {
    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'name' => 'Thandi Mokoena',
        'email' => 'thandi@example.com',
        'phone' => '+27825550001',
        'is_company_admin' => true,
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.member.is_company_admin', true);

    expect(User::where('email', 'thandi@example.com')->sole()->isCompanyAdminOf($this->company))->toBeTrue();
});

it('also sends an sms when sms delivery is enabled', function () {
    config(['pushservice.providers.sms' => true]);

    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'name' => 'Thandi Mokoena',
        'email' => 'thandi@example.com',
        'phone' => '+27825550001',
    ], $this->headers)->assertCreated();

    Notification::assertSentTo(
        User::where('email', 'thandi@example.com')->sole(),
        CompanyInvitationNotification::class,
        fn (CompanyInvitationNotification $notification, array $channels): bool => $channels === ['mail', 'vonage'],
    );
});

it('adds an existing user without sending an invitation', function (string $field) {
    $existing = User::factory()->forCompany(Company::factory())->create([
        'phone' => '+27825550001',
        'email' => 'thandi@example.com',
    ]);

    $payload = [
        'name' => 'Thandi',
        'email' => $field === 'email' ? 'thandi@example.com' : 'other@example.com',
        'phone' => $field === 'phone' ? '+27825550001' : '+27825559999',
    ];

    test()->postJson(route('api.v1.invitations.store', $this->company), $payload, $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'added')
        ->assertJsonPath('data.member.id', $existing->id);

    expect($existing->fresh()->belongsToCompany($this->company))->toBeTrue()
        ->and(CompanyInvitation::count())->toBe(0);

    Notification::assertNothingSent();
})->with(['phone', 'email']);

it('rejects a phone and e-mail that belong to different users', function () {
    User::factory()->create(['phone' => '+27825550001']);
    User::factory()->create(['email' => 'thandi@example.com']);

    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'name' => 'Thandi',
        'email' => 'thandi@example.com',
        'phone' => '+27825550001',
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('validates the invitation payload', function () {
    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'email' => 'not-an-email',
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone']);
});

it('forbids non-admin members from managing invitations', function () {
    $member = User::factory()->forCompany($this->company)->create();
    $headers = ['Authorization' => 'Bearer '.app(IssueUserApiToken::class)->handle($member)['plain_text_token']];
    $invitation = CompanyInvitation::factory()->for($this->company)->create();

    test()->postJson(route('api.v1.invitations.store', $this->company), [
        'name' => 'Thandi',
        'email' => 'thandi@example.com',
        'phone' => '+27825550001',
    ], $headers)->assertForbidden();

    test()->getJson(route('api.v1.invitations.index', $this->company), $headers)->assertForbidden();
    test()->postJson(route('api.v1.invitations.resend', [$this->company, $invitation]), [], $headers)->assertForbidden();
    test()->deleteJson(route('api.v1.invitations.destroy', [$this->company, $invitation]), [], $headers)->assertForbidden();
});

it('lists pending invitations only', function () {
    $pendingUser = User::factory()->forCompany($this->company, true)->create(['name' => 'Pending Person']);
    $pending = CompanyInvitation::factory()->for($this->company)->for($pendingUser)->create([
        'invited_by_user_id' => $this->admin->id,
    ]);
    CompanyInvitation::factory()->for($this->company)->accepted()->create();
    CompanyInvitation::factory()->for($this->company)->revoked()->create();
    CompanyInvitation::factory()->create();

    test()->getJson(route('api.v1.invitations.index', $this->company), $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.name', 'Pending Person')
        ->assertJsonPath('data.0.is_company_admin', true)
        ->assertJsonPath('data.0.invited_by', 'Anele')
        ->assertJsonPath('data.0.is_expired', false);
});

it('resends an invitation with a new token and expiry', function () {
    $user = User::factory()->forCompany($this->company)->create();
    $invitation = CompanyInvitation::factory()->for($this->company)->for($user)->expired()->create();
    $originalHash = $invitation->token_hash;

    test()->postJson(route('api.v1.invitations.resend', [$this->company, $invitation]), [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.is_expired', false);

    $invitation->refresh();

    expect($invitation->token_hash)->not->toBe($originalHash)
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Notification::assertSentTo($user, CompanyInvitationNotification::class);
});

it('revokes an invitation and removes the never-activated account', function () {
    $user = User::factory()->forCompany($this->company)->create();
    $invitation = CompanyInvitation::factory()->for($this->company)->for($user)->create();

    test()->deleteJson(route('api.v1.invitations.destroy', [$this->company, $invitation]), [], $this->headers)
        ->assertNoContent();

    expect(User::find($user->id))->toBeNull()
        ->and(CompanyInvitation::find($invitation->id))->toBeNull();
});

it('keeps the account when revoking if the person belongs to another company', function () {
    $other = Company::factory()->create();
    $user = User::factory()->forCompany($this->company)->create();
    $user->companies()->attach($other->id, ['is_company_admin' => false]);
    $invitation = CompanyInvitation::factory()->for($this->company)->for($user)->create();

    test()->deleteJson(route('api.v1.invitations.destroy', [$this->company, $invitation]), [], $this->headers)
        ->assertNoContent();

    $user->refresh();

    expect($user->belongsToCompany($this->company))->toBeFalse()
        ->and($user->belongsToCompany($other))->toBeTrue()
        ->and($invitation->fresh()->revoked_at)->not->toBeNull();
});

it('returns 404 for an invitation from another company', function () {
    $foreign = CompanyInvitation::factory()->create();

    test()->postJson(route('api.v1.invitations.resend', [$this->company, $foreign]), [], $this->headers)->assertNotFound();
    test()->deleteJson(route('api.v1.invitations.destroy', [$this->company, $foreign]), [], $this->headers)->assertNotFound();
});

it('refuses to resend or revoke an accepted invitation', function () {
    $invitation = CompanyInvitation::factory()->for($this->company)->accepted()->create();

    test()->postJson(route('api.v1.invitations.resend', [$this->company, $invitation]), [], $this->headers)
        ->assertUnprocessable();
    test()->deleteJson(route('api.v1.invitations.destroy', [$this->company, $invitation]), [], $this->headers)
        ->assertUnprocessable();
});
