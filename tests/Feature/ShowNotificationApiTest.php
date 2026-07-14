<?php

use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('lists notifications for the authenticated company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    PushNotification::factory()->count(2)->create(['company_id' => $company->id]);
    PushNotification::factory()->create(['company_id' => $otherCompany->id]);

    $response = test()->getJson(
        route('api.v1.notifications.index', $company),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    );

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('shows a notification with its deliveries', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $notification = PushNotification::factory()->forUser($user)->create([
        'company_id' => $company->id,
    ]);

    NotificationDelivery::factory()->for($notification)->for($user)->create([
        'channel' => 'mail',
        'status' => NotificationDelivery::STATUS_SENT,
        'sent_at' => now(),
    ]);

    test()->getJson(
        route('api.v1.notifications.show', [$company, $notification]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )
        ->assertSuccessful()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.deliveries.0.channel', 'mail')
        ->assertJsonPath('data.deliveries.0.status', NotificationDelivery::STATUS_SENT);
});

it('returns 404 when a notification belongs to another company', function () {
    $company = Company::factory()->create();
    $notification = PushNotification::factory()->create();

    test()->getJson(
        route('api.v1.notifications.show', [$company, $notification]),
        ['Authorization' => 'Bearer '.$company->hmac_secret],
    )->assertNotFound();
});

it('queues a scheduled notification with scheduled status', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    storeNotification($company, [
        'target' => ['type' => 'user', 'id' => $user->id],
        'title' => 'Later',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', PushNotification::STATUS_SCHEDULED);

    expect(PushNotification::firstOrFail())
        ->scheduled_at->not->toBeNull();
});
