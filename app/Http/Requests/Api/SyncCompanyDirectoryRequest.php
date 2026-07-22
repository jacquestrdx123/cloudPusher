<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncCompanyDirectoryRequest extends FormRequest
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
            'users' => ['sometimes', 'array'],
            'users.*.external_id' => ['nullable', 'string', 'max:255'],
            'users.*.name' => ['nullable', 'string', 'max:255'],
            'users.*.email' => ['required', 'email', 'max:255'],
            'users.*.phone' => ['nullable', 'string', 'max:32'],
            'users.*.locale' => ['nullable', 'string', 'max:10'],
            'users.*.is_company_admin' => ['nullable', 'boolean'],

            'groups' => ['sometimes', 'array'],
            'groups.*.external_id' => ['nullable', 'string', 'max:255'],
            'groups.*.name' => ['nullable', 'string', 'max:255'],
            'groups.*.slug' => ['nullable', 'string', 'max:255'],
            'groups.*.members' => ['nullable', 'array'],
            'groups.*.members.*.external_id' => ['nullable', 'string', 'max:255'],
            'groups.*.members.*.email' => ['nullable', 'email', 'max:255'],
            'groups.*.members.*.phone' => ['nullable', 'string', 'max:32'],

            'delete_missing_users' => ['sometimes', 'boolean'],
            'delete_missing_groups' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function users(): array
    {
        /** @var array<int, array<string, mixed>> $users */
        $users = $this->array('users');

        return array_values($users);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        /** @var array<int, array<string, mixed>> $groups */
        $groups = $this->array('groups');

        return array_values($groups);
    }

    public function deleteMissingUsers(): bool
    {
        return $this->boolean('delete_missing_users');
    }

    public function deleteMissingGroups(): bool
    {
        return $this->boolean('delete_missing_groups');
    }
}
