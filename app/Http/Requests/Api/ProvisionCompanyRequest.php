<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProvisionCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'default_channels' => ['nullable', 'array'],
            'default_channels.*' => ['string', 'in:push,mail,sms'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, slug?: string|null, default_channels?: array<int, string>|null, is_active?: bool|null}
     */
    public function companyData(): array
    {
        $data = ['name' => $this->string('name')->toString()];

        if ($this->filled('slug')) {
            $data['slug'] = $this->string('slug')->toString();
        }

        if ($this->has('default_channels')) {
            /** @var array<int, string> $channels */
            $channels = $this->array('default_channels');
            $data['default_channels'] = array_values(array_unique($channels));
        }

        if ($this->has('is_active')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
