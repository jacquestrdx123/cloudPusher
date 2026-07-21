<?php

namespace App\Http\Resources\Api;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserNotification
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
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->data,
            'channel' => $this->channel,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'read' => $this->isRead(),
            'created_at' => $this->created_at,
            'push_notification_id' => $this->push_notification_id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company === null ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
            ]),
        ];
    }
}
