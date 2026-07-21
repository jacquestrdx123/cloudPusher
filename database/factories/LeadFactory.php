<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company_name' => fake()->company(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'message' => fake()->paragraph(),
            'status' => LeadStatus::New,
            'notes' => null,
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeadStatus::Contacted,
            'notes' => 'Reached out via email.',
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeadStatus::Closed,
            'notes' => 'Not a fit.',
        ]);
    }
}
