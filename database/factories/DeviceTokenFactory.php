<?php

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement([DeviceToken::PLATFORM_FCM, DeviceToken::PLATFORM_APNS]),
            'token' => Str::random(64),
            'name' => fake()->randomElement(['iPhone', 'Pixel', 'iPad', 'Chrome']),
            'last_used_at' => now(),
        ];
    }

    public function fcm(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => DeviceToken::PLATFORM_FCM,
        ]);
    }

    public function apns(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => DeviceToken::PLATFORM_APNS,
        ]);
    }
}
