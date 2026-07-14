<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();
        $user = User::factory()->for($company);

        return [
            'company_id' => $company,
            'user_id' => $user,
            'push_notification_id' => PushNotification::factory()->for($user),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'data' => [],
            'channel' => 'fcm',
            'delivered_at' => now(),
            'read_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'push_notification_id' => PushNotification::factory()->forUser($user),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }
}
