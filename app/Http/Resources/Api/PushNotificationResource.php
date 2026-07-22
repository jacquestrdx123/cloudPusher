<?php

namespace App\Http\Resources\Api;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PushNotification
 */
class PushNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'target_type' => $this->target_type,
            'user_id' => $this->user_id,
            'user_group_id' => $this->user_group_id,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'sound' => $this->sound,
            'category' => $this->category,
            'android_channel_id' => $this->android_channel_id,
            'payload' => $this->data,
            'channels' => $this->channels,
            'recipients_count' => $this->recipients_count,
            'scheduled_at' => $this->scheduled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deliveries' => NotificationDeliveryResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
