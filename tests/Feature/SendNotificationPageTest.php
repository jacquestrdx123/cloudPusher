<?php

use App\Filament\Pages\SendNotification;
use App\Jobs\ProcessPushNotification;
use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('queues a push-only notification for the selected tenant user', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();
    $recipient = User::factory()->forCompany($company)->create();
    DeviceToken::factory()->fcm()->for($recipient)->create();

    $this->actingAs($admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($company);

    Livewire::test(SendNotification::class)
        ->assertOk()
        ->fillForm([
            'target_type' => PushNotification::TARGET_USER,
            'user_id' => $recipient->id,
            'title' => 'Hello from admin',
            'body' => 'Push check',
            'channels' => ['push'],
        ])
        ->call('send')
        ->assertNotified();

    $notification = PushNotification::query()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->company_id)->toBe($company->id)
        ->and($notification->user_id)->toBe($recipient->id)
        ->and($notification->channels)->toBe(['push'])
        ->and($notification->status)->toBe(PushNotification::STATUS_PENDING);

    Queue::assertPushed(ProcessPushNotification::class, function (ProcessPushNotification $job) use ($notification): bool {
        return $job->pushNotification->is($notification);
    });
});

it('defaults the send form to the push channel only', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company, true)->create();

    $this->actingAs($admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($company);

    Livewire::test(SendNotification::class)
        ->assertOk()
        ->assertFormSet([
            'target_type' => PushNotification::TARGET_USER,
            'channels' => ['push'],
        ]);
});
