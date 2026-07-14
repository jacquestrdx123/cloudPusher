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
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $company = Company::factory()->create([
            'name' => 'Acme Inc',
            'slug' => 'acme',
            'default_channels' => ['push', 'mail'],
        ]);

        $users = User::factory()->for($company)->count(5)->create();
        $users->each(fn (User $user) => DeviceToken::factory()->fcm()->for($user)->create());

        $ops = UserGroup::factory()->for($company)->create(['name' => 'Ops Team', 'slug' => 'ops']);
        $ops->users()->attach($users->take(3));
    }
}
