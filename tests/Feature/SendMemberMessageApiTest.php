<?php

use App\Actions\IssueUserApiToken;
use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();

    $this->company = Company::factory()->create();
    $this->sender = User::factory()->forCompany($this->company)->create(['name' => 'Lerato']);
    $this->token = app(IssueUserApiToken::class)->handle($this->sender)['plain_text_token'];
});

/**
 * @param  array<string, mixed>  $payload
 */
function sendMemberMessage(Company $company, array $payload, string $token): TestResponse
{
    return test()->postJson(
        route('api.v1.messages.store', $company),
        $payload,
        ['Authorization' => 'Bearer '.$token],
    );
}

it('lets a regular member message a single colleague', function () {
    $colleague = User::factory()->forCompany($this->company)->create();

    $response = sendMemberMessage($this->company, [
        'title' => 'Truck 4 is loaded',
        'body' => 'Ready at bay 2.',
        'recipients' => ['type' => 'users', 'user_ids' => [$colleague->id]],
    ], $this->token)
        ->assertStatus(202)
        ->assertJsonPath('data.sent', 1);

    $notification = PushNotification::findOrFail($response->json('data.notification_ids.0'));

    expect($notification->target_type)->toBe(PushNotification::TARGET_USER)
        ->and($notification->user_id)->toBe($colleague->id)
        ->and($notification->sent_by_user_id)->toBe($this->sender->id)
        ->and($notification->sender->is($this->sender))->toBeTrue()
        ->and($notification->channels)->toBe(['push'])
        ->and($notification->data)->toBe(['sender_id' => $this->sender->id, 'sender_name' => 'Lerato'])
        ->and($notification->body)->toBe('Ready at bay 2.');

    Queue::assertPushed(ProcessPushNotification::class, 1);
});

it('creates one notification per selected colleague', function () {
    $colleagues = User::factory()->forCompany($this->company)->count(3)->create();

    sendMemberMessage($this->company, [
        'title' => 'Team meeting at 10',
        'recipients' => ['type' => 'users', 'user_ids' => $colleagues->pluck('id')->all()],
    ], $this->token)
        ->assertStatus(202)
        ->assertJsonPath('data.sent', 3)
        ->assertJsonCount(3, 'data.notification_ids');

    expect(PushNotification::where('sent_by_user_id', $this->sender->id)->pluck('user_id')->sort()->values()->all())
        ->toBe($colleagues->pluck('id')->sort()->values()->all());

    Queue::assertPushed(ProcessPushNotification::class, 3);
});

it('messages a company group', function () {
    $group = UserGroup::factory()->for($this->company)->create();

    sendMemberMessage($this->company, [
        'title' => 'Drivers: road closed',
        'recipients' => ['type' => 'group', 'group_id' => $group->id],
    ], $this->token)
        ->assertStatus(202)
        ->assertJsonPath('data.sent', 1);

    $notification = PushNotification::sole();

    expect($notification->target_type)->toBe(PushNotification::TARGET_GROUP)
        ->and($notification->user_group_id)->toBe($group->id)
        ->and($notification->sent_by_user_id)->toBe($this->sender->id);

    Queue::assertPushed(ProcessPushNotification::class, 1);
});

it('broadcasts to the whole company', function () {
    sendMemberMessage($this->company, [
        'title' => 'Office closed Friday',
        'recipients' => ['type' => 'broadcast'],
    ], $this->token)
        ->assertStatus(202)
        ->assertJsonPath('data.sent', 1);

    expect(PushNotification::sole())
        ->target_type->toBe(PushNotification::TARGET_BROADCAST)
        ->company_id->toBe($this->company->id);
});

it('rejects recipients from another company', function () {
    $outsider = User::factory()->forCompany(Company::factory())->create();
    $colleague = User::factory()->forCompany($this->company)->create();

    sendMemberMessage($this->company, [
        'title' => 'Hello',
        'recipients' => ['type' => 'users', 'user_ids' => [$colleague->id, $outsider->id]],
    ], $this->token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients.user_ids');

    expect(PushNotification::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a group from another company', function () {
    $foreignGroup = UserGroup::factory()->create();

    sendMemberMessage($this->company, [
        'title' => 'Hello',
        'recipients' => ['type' => 'group', 'group_id' => $foreignGroup->id],
    ], $this->token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients.group_id');

    Queue::assertNothingPushed();
});

it('validates the message payload', function () {
    sendMemberMessage($this->company, [
        'recipients' => ['type' => 'users', 'user_ids' => []],
    ], $this->token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'recipients.user_ids']);

    sendMemberMessage($this->company, [
        'title' => 'Hi',
        'recipients' => ['type' => 'everyone'],
    ], $this->token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients.type');

    sendMemberMessage($this->company, [
        'title' => 'Hi',
        'recipients' => ['type' => 'group'],
    ], $this->token)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients.group_id');
});

it('rejects senders who are not members of the company', function () {
    $otherCompany = Company::factory()->create();

    sendMemberMessage($otherCompany, [
        'title' => 'Hello',
        'recipients' => ['type' => 'broadcast'],
    ], $this->token)->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rate limits message sending per user', function () {
    config(['pushservice.auth.message_rate_limit' => 1]);

    $payload = ['title' => 'Hi', 'recipients' => ['type' => 'broadcast']];

    sendMemberMessage($this->company, $payload, $this->token)->assertStatus(202);

    sendMemberMessage($this->company, $payload, $this->token)
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'You are sending messages too quickly. Please wait a minute and try again.');
});
