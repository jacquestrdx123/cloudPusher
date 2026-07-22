<?php

namespace App\Models;

use Database\Factories\UserGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property string|null $external_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 */
#[Fillable(['company_id', 'name', 'slug', 'external_id'])]
class UserGroup extends Model
{
    /** @use HasFactory<UserGroupFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (UserGroup $group): void {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name);
            }
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')->withTimestamps();
    }
}
