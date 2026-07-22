<?php

namespace App\Http\Resources\Api;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class ProvisionedCompanyResource extends JsonResource
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
            'hmac_secret' => $this->hmac_secret,
            'default_channels' => $this->resolvedDefaultChannels(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
