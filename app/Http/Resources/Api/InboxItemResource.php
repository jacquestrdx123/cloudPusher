<?php

namespace App\Http\Resources\Api;

use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationDelivery
 */
class InboxItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'status' => $this->status,
            'error' => $this->error,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'notification' => [
                'id' => $this->pushNotification->id,
                'title' => $this->pushNotification->title,
                'body' => $this->pushNotification->body,
                'payload' => $this->pushNotification->data,
                'channels' => $this->pushNotification->channels,
            ],
        ];
    }
}
