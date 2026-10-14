<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyInvitationRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'is_company_admin' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string, is_company_admin: bool}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->toString(),
            'phone' => $this->string('phone')->trim()->toString(),
            'is_company_admin' => $this->boolean('is_company_admin'),
        ];
    }
}
