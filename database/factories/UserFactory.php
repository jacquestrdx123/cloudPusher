<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use WeakMap;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @var WeakMap<User, array{company: Company|Factory<Company>|null, admin: bool}>|null
     */
    private static ?WeakMap $pendingMemberships = null;

    /**
     * @return WeakMap<User, array{company: Company|Factory<Company>|null, admin: bool}>
     */
    private static function pendingMemberships(): WeakMap
    {
        return self::$pendingMemberships ??= new WeakMap;
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'locale' => null,
            'is_admin' => false,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->is_admin) {
                return;
            }

            $pending = self::pendingMemberships()[$user] ?? null;

            if ($pending !== null) {
                if ($pending['company'] !== null) {
                    $company = $pending['company'] instanceof Company
                        ? $pending['company']
                        : $pending['company']->create();

                    $user->companies()->syncWithoutDetaching([
                        $company->getKey() => ['is_company_admin' => $pending['admin']],
                    ]);
                } elseif ($pending['admin']) {
                    if ($user->companies()->exists()) {
                        $user->companies()->newPivotQuery()
                            ->where('user_id', $user->id)
                            ->update(['is_company_admin' => true]);
                    } else {
                        $user->companies()->attach(Company::factory()->create()->id, [
                            'is_company_admin' => true,
                        ]);
                    }
                }

                unset(self::pendingMemberships()[$user]);

                return;
            }

            if ($user->companies()->exists()) {
                return;
            }

            $user->companies()->attach(Company::factory()->create()->id, [
                'is_company_admin' => false,
            ]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a panel admin with no company.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * @param  Company|Factory<Company>  $company
     */
    public function forCompany(Company|Factory $company, bool $isCompanyAdmin = false): static
    {
        return $this->afterMaking(function (User $user) use ($company, $isCompanyAdmin): void {
            $existing = self::pendingMemberships()[$user] ?? ['company' => null, 'admin' => false];

            self::pendingMemberships()[$user] = [
                'company' => $company,
                'admin' => $isCompanyAdmin || $existing['admin'],
            ];
        });
    }

    public function companyAdmin(): static
    {
        return $this->afterMaking(function (User $user): void {
            $existing = self::pendingMemberships()[$user] ?? ['company' => null, 'admin' => false];

            self::pendingMemberships()[$user] = [
                'company' => $existing['company'],
                'admin' => true,
            ];
        });
    }
}
