<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $locale
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'locale', 'is_admin', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasLocalePreference, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Company, $this, CompanyUser>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(CompanyUser::class)
            ->withPivot('is_company_admin', 'external_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<UserGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'group_user')->withTimestamps();
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * @return HasMany<UserApiToken, $this>
     */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(UserApiToken::class);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('is_admin', true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMembersOf(Builder $query, Company $company): Builder
    {
        return $query->whereHas(
            'companies',
            fn (Builder $companies) => $companies->whereKey($company->getKey()),
        );
    }

    public function belongsToCompany(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        if ($this->relationLoaded('companies')) {
            return $this->companies->contains(
                fn (Company $memberCompany): bool => (int) $memberCompany->getKey() === (int) $company->getKey(),
            );
        }

        return $this->companies()->whereKey($company->getKey())->exists();
    }

    public function isCompanyAdminOf(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        if ($this->relationLoaded('companies')) {
            $membership = $this->companies->first(
                fn (Company $memberCompany): bool => (int) $memberCompany->getKey() === (int) $company->getKey(),
            );

            return $membership !== null && (bool) $membership->pivot->is_company_admin;
        }

        return $this->companies()
            ->whereKey($company->getKey())
            ->wherePivot('is_company_admin', true)
            ->exists();
    }

    /**
     * Global (platform) administrator — can manage every company.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->is_admin;
    }

    /**
     * Company administrator of at least one company.
     */
    public function isCompanyAdmin(): bool
    {
        if ($this->relationLoaded('companies')) {
            return $this->companies->contains(
                fn (Company $company): bool => (bool) $company->pivot->is_company_admin,
            );
        }

        return $this->companies()->wherePivot('is_company_admin', true)->exists();
    }

    public function canAdministerCompany(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }

        if ($this->isGlobalAdmin()) {
            return true;
        }

        return $this->isCompanyAdminOf($company);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isGlobalAdmin() || $this->isCompanyAdmin();
    }

    /**
     * @return Collection<int, Company>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isGlobalAdmin()) {
            return Company::query()->orderBy('name')->get();
        }

        return $this->companies()
            ->wherePivot('is_company_admin', true)
            ->orderBy('name')
            ->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Company) {
            return false;
        }

        return $this->canAdministerCompany($tenant);
    }

    public function canRegisterTenants(Panel $panel): bool
    {
        return $this->isGlobalAdmin();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->getTenants($panel)->first();
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * FCM device tokens for push notifications.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens
            ->where('platform', DeviceToken::PLATFORM_FCM)
            ->pluck('token')
            ->all();
    }

    /**
     * APNs device tokens for push notifications.
     *
     * @return array<int, string>
     */
    public function routeNotificationForApn(): array
    {
        return $this->deviceTokens
            ->where('platform', DeviceToken::PLATFORM_APNS)
            ->pluck('token')
            ->all();
    }

    /**
     * Route SMS notifications to the user's phone number.
     */
    public function routeNotificationForVonage(): ?string
    {
        return $this->phone;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
