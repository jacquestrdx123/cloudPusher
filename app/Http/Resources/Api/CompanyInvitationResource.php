<?php

namespace App\Http\Resources\Api;

use App\Models\CompanyInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyInvitation
 */
class CompanyInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
            'is_company_admin' => $this->user->isCompanyAdminOf($this->company),
            'invited_by' => $this->invitedBy?->name,
            'expires_at' => $this->expires_at->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
