<?php

namespace App\Http\Resources\Api;

use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserGroup
 */
class UserGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'users_count' => (int) ($this->users_count ?? 0),
        ];
    }
}
