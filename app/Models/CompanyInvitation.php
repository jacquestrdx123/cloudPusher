<?php

namespace App\Models;

use Database\Factories\CompanyInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $invited_by_user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read User $user
 * @property-read User|null $invitedBy
 */
#[Fillable(['company_id', 'user_id', 'invited_by_user_id', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
class CompanyInvitation extends Model
{
    /** @use HasFactory<CompanyInvitationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Invitations that have been neither accepted nor revoked (expired ones included).
     *
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at');
    }

    public static function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function findByPlainToken(string $plainTextToken): ?self
    {
        return static::query()
            ->with(['company', 'user'])
            ->where('token_hash', static::hashToken($plainTextToken))
            ->first();
    }

    public static function defaultExpiry(): Carbon
    {
        return Carbon::now()->addDays(max((int) config('pushservice.invitation_ttl_days', 7), 1));
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }
}
