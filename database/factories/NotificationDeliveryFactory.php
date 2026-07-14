<?php

namespace Database\Factories;

use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'push_notification_id' => PushNotification::factory(),
            'user_id' => User::factory(),
            'channel' => fake()->randomElement(['fcm', 'apns', 'mail', 'sms']),
            'status' => NotificationDelivery::STATUS_PENDING,
            'error' => null,
            'sent_at' => null,
        ];
    }
}
