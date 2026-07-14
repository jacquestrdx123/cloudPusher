<?php

namespace App\Models;

use Database\Factories\PushNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $target_type
 * @property int|null $user_id
 * @property int|null $user_group_id
 * @property string $title
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property array<int, string> $channels
 * @property string $status
 * @property int $recipients_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read User $user
 * @property-read UserGroup $group
 */
#[Fillable([
    'company_id', 'target_type', 'user_id', 'user_group_id',
    'title', 'body', 'data', 'channels', 'status', 'recipients_count',
])]
class PushNotification extends Model
{
    /** @use HasFactory<PushNotificationFactory> */
    use HasFactory;

    public const string TARGET_USER = 'user';

    public const string TARGET_GROUP = 'group';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'recipients_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'channels' => 'array',
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
     * @return BelongsTo<UserGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
