<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'name', 'token_hash', 'last_used_at', 'expires_at'])]
#[Hidden(['token_hash'])]
class UserApiToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    /**
     * @return array{token: UserApiToken, plain_text_token: string}
     */
    public static function issue(User $user, string $name, ?DateTimeInterface $expiresAt = null): array
    {
        $plainTextToken = Str::random(64);

        $token = static::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => static::hashToken($plainTextToken),
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'plain_text_token' => $plainTextToken,
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
