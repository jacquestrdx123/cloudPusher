<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Global Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $acme = Company::factory()->create([
            'name' => 'Acme Inc',
            'slug' => 'acme',
            'default_channels' => ['push', 'mail'],
        ]);

        User::factory()->forCompany($acme, true)->create([
            'name' => 'Acme Admin',
            'email' => 'company@example.com',
            'phone' => '+27821110001',
            'password' => 'password',
        ]);

        $users = User::factory()->forCompany($acme)->count(5)->create();
        $users->each(fn (User $user) => DeviceToken::factory()->fcm()->for($user)->create());

        $ops = UserGroup::factory()->for($acme)->create(['name' => 'Ops Team', 'slug' => 'ops']);
        $ops->users()->attach($users->take(3));

        $beta = Company::factory()->create([
            'name' => 'Beta Ltd',
            'slug' => 'beta',
            'default_channels' => ['push'],
        ]);

        User::factory()->forCompany($beta, true)->create([
            'name' => 'Beta Admin',
            'email' => 'beta-admin@example.com',
            'phone' => '+27821110002',
            'password' => 'password',
        ]);

        User::factory()->forCompany($beta)->count(2)->create();
    }
}
