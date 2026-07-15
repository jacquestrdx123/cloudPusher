<?php

namespace Database\Factories;

use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\UserRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<UserRegistration>
 */
class UserRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'password' => Hash::make('password'),
            'status' => UserRegistrationStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'user_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserRegistrationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserRegistrationStatus::Rejected,
            'reviewed_at' => now(),
            'review_notes' => 'Does not meet requirements.',
        ]);
    }
}
