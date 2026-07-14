<?php

namespace App\Models;

use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $push_notification_id
 * @property int $user_id
 * @property string $channel
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PushNotification $pushNotification
 * @property-read User $user
 */
#[Fillable(['push_notification_id', 'user_id', 'channel', 'status', 'error', 'sent_at'])]
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PushNotification, $this>
     */
    public function pushNotification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
