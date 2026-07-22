<?php

namespace App\Actions;

use App\Models\Company;

class ProvisionCompany
{
    /**
     * Create a company on behalf of an upstream system, or return the existing
     * company when one already matches the provided slug (idempotent retries).
     *
     * @param  array{name: string, slug?: string|null, default_channels?: array<int, string>|null, is_active?: bool|null}  $data
     * @return array{company: Company, created: bool}
     */
    public function handle(array $data): array
    {
        $slug = isset($data['slug']) && filled($data['slug'])
            ? (string) $data['slug']
            : null;

        $existing = $slug !== null
            ? Company::query()->where('slug', $slug)->first()
            : null;

        if ($existing !== null) {
            $existing->name = $data['name'];

            if (isset($data['default_channels'])) {
                $existing->default_channels = $data['default_channels'];
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
                $existing->is_active = (bool) $data['is_active'];
            }

            $existing->save();

            return ['company' => $existing, 'created' => false];
        }

        $company = Company::query()->create(array_filter([
            'name' => $data['name'],
            'slug' => $slug,
            'default_channels' => $data['default_channels'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ], fn ($value): bool => $value !== null));

        return ['company' => $company, 'created' => true];
    }
}
