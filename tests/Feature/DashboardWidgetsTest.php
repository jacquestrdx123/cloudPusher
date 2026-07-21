<?php

use App\Filament\Widgets\ChannelBreakdownChart;
use App\Filament\Widgets\DeliveriesChart;
use App\Filament\Widgets\DeliveryStatsOverview;
use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->otherCompany = Company::factory()->create();
    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);

    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($this->company);
});

it('renders delivery overview stats scoped to the current tenant', function () {
    $member = User::factory()->forCompany($this->company)->create();
    $otherMember = User::factory()->forCompany($this->otherCompany)->create();

    $notification = PushNotification::factory()->for($this->company)->forUser($member)->create([
        'status' => PushNotification::STATUS_SENT,
        'created_at' => now()->subDay(),
    ]);
    PushNotification::factory()->for($this->company)->forUser($member)->create([
        'status' => PushNotification::STATUS_FAILED,
        'created_at' => now()->subDays(2),
    ]);
    PushNotification::factory()->for($this->otherCompany)->forUser($otherMember)->create();

    NotificationDelivery::factory()->for($notification)->for($member)->sent()->create([
        'channel' => 'apns',
        'created_at' => now()->subDay(),
    ]);
    NotificationDelivery::factory()->for($notification)->for($member)->failed()->create([
        'channel' => 'fcm',
        'created_at' => now()->subDay(),
    ]);
    NotificationDelivery::factory()
        ->for(PushNotification::factory()->for($this->otherCompany)->forUser($otherMember))
        ->for($otherMember)
        ->sent()
        ->create();

    DeviceToken::factory()->for($member)->apns()->create();
    DeviceToken::factory()->for($otherMember)->fcm()->create();

    Livewire::test(DeliveryStatsOverview::class)
        ->assertOk()
        ->assertSee('Delivery overview')
        ->assertSee('2')
        ->assertSee('1')
        ->assertSee('Device tokens');
});

it('renders the deliveries trend chart for the tenant', function () {
    $member = User::factory()->forCompany($this->company)->create();
    $notification = PushNotification::factory()->for($this->company)->forUser($member)->create();

    NotificationDelivery::factory()->for($notification)->for($member)->sent()->count(3)->create([
        'created_at' => now()->subDays(1),
    ]);
    NotificationDelivery::factory()->for($notification)->for($member)->failed()->count(2)->create([
        'created_at' => now()->subDays(1),
    ]);

    Livewire::test(DeliveriesChart::class)
        ->assertOk()
        ->assertSee('Deliveries (14 days)');
});

it('renders the channel breakdown chart for the tenant', function () {
    $member = User::factory()->forCompany($this->company)->create();
    $notification = PushNotification::factory()->for($this->company)->forUser($member)->create();

    NotificationDelivery::factory()->for($notification)->for($member)->sent()->create([
        'channel' => 'apns',
    ]);
    NotificationDelivery::factory()->for($notification)->for($member)->sent()->create([
        'channel' => 'fcm',
    ]);
    NotificationDelivery::factory()->for($notification)->for($member)->sent()->create([
        'channel' => 'mail',
    ]);

    Livewire::test(ChannelBreakdownChart::class)
        ->assertOk()
        ->assertSee('Channels')
        ->assertSee('APNS')
        ->assertSee('FCM')
        ->assertSee('MAIL');
});
