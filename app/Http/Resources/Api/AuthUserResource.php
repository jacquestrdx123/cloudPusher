<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $companies = $this->relationLoaded('companies')
            ? $this->companies
            : $this->companies()->where('is_active', true)->get();

        $mappedCompanies = $companies->map(fn ($company) => [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'is_company_admin' => (bool) $company->pivot->is_company_admin,
        ])->values()->all();

        $firstCompany = $companies->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'companies' => $mappedCompanies,
            // Compatibility for clients still expecting a single company.
            'company_id' => $firstCompany?->id,
            'is_company_admin' => $firstCompany !== null && (bool) $firstCompany->pivot->is_company_admin,
            'company' => $firstCompany === null ? null : [
                'id' => $firstCompany->id,
                'name' => $firstCompany->name,
                'slug' => $firstCompany->slug,
            ],
        ];
    }
}
