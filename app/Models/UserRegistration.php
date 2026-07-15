<?php

namespace App\Models;

use App\Enums\UserRegistrationStatus;
use Database\Factories\UserRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property UserRegistrationStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read User|null $reviewer
 * @property-read User|null $user
 */
#[Fillable([
    'company_id',
    'name',
    'email',
    'phone',
    'password',
    'status',
    'reviewed_by',
    'reviewed_at',
    'review_notes',
    'user_id',
])]
#[Hidden(['password'])]
class UserRegistration extends Model
{
    /** @use HasFactory<UserRegistrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserRegistrationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<UserRegistration>  $query
     * @return Builder<UserRegistration>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', UserRegistrationStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === UserRegistrationStatus::Pending;
    }
}
