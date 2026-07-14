<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'target_type' => PushNotification::TARGET_USER,
            'user_id' => User::factory()->for($company),
            'user_group_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'data' => [],
            'channels' => ['push'],
            'status' => PushNotification::STATUS_PENDING,
            'recipients_count' => 0,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $user->company_id,
            'target_type' => PushNotification::TARGET_USER,
            'user_id' => $user->id,
        ]);
    }

    public function forGroup(UserGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $group->company_id,
            'target_type' => PushNotification::TARGET_GROUP,
            'user_group_id' => $group->id,
        ]);
    }
}
