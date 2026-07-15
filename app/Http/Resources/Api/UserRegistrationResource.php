<?php

namespace App\Http\Resources\Api;

use App\Models\UserRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserRegistration
 */
class UserRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'user_id' => $this->user_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
            ]),
        ];
    }
}
