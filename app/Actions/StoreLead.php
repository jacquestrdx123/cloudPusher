<?php

namespace App\Actions;

use App\Enums\LeadStatus;
use App\Models\Lead;

class StoreLead
{
    /**
     * @param  array{name: string, email: string, company_name: string, phone?: string|null, message: string}  $data
     */
    public function handle(array $data): Lead
    {
        return Lead::query()->create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'company_name' => $data['company_name'],
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'message' => $data['message'],
            'status' => LeadStatus::New,
        ]);
    }
}
