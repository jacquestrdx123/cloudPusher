<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $hmac_secret
 * @property array<int, string>|null $default_channels
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'hmac_secret', 'default_channels', 'is_active'])]
class Company extends Model implements HasCurrentTenantLabel, HasName
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            if (empty($company->slug)) {
                $company->slug = static::uniqueSlug($company->name);
            }

            if (empty($company->hmac_secret)) {
                $company->hmac_secret = Str::random(48);
            }
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, CompanyUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CompanyUser::class)
            ->withPivot('is_company_admin', 'external_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(UserGroup::class);
    }

    /**
     * @return HasMany<PushNotification, $this>
     */
    public function pushNotifications(): HasMany
    {
        return $this->hasMany(PushNotification::class);
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * Channels used when a webhook payload does not specify any.
     *
     * @return array<int, string>
     */
    public function resolvedDefaultChannels(): array
    {
        return $this->default_channels ?: ['push'];
    }

    /**
     * @param  Builder<Company>  $query
     * @return Builder<Company>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Current company';
    }
}
