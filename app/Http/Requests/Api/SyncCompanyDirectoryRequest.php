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

    protected function prepareForValidation(): void
    {
        $users = $this->input('users');

        if (is_array($users)) {
            $this->merge([
                'users' => array_map(fn (mixed $user): mixed => $this->normalizeMobileAlias($user), $users),
            ]);
        }

        $groups = $this->input('groups');

        if (! is_array($groups)) {
            return;
        }

        $this->merge([
            'groups' => array_map(function (mixed $group): mixed {
                if (! is_array($group) || ! isset($group['members']) || ! is_array($group['members'])) {
                    return $group;
                }

                $group['members'] = array_map(
                    fn (mixed $member): mixed => $this->normalizeMobileAlias($member),
                    $group['members'],
                );

                return $group;
            }, $groups),
        ]);
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
            'users.*.mobile_number' => ['required', 'string', 'max:32'],
            'users.*.email' => ['nullable', 'email', 'max:255'],
            'users.*.locale' => ['nullable', 'string', 'max:10'],
            'users.*.is_company_admin' => ['nullable', 'boolean'],

            'groups' => ['sometimes', 'array'],
            'groups.*.external_id' => ['nullable', 'string', 'max:255'],
            'groups.*.name' => ['nullable', 'string', 'max:255'],
            'groups.*.slug' => ['nullable', 'string', 'max:255'],
            'groups.*.members' => ['nullable', 'array'],
            'groups.*.members.*.external_id' => ['nullable', 'string', 'max:255'],
            'groups.*.members.*.email' => ['nullable', 'email', 'max:255'],
            'groups.*.members.*.mobile_number' => ['nullable', 'string', 'max:32'],

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

    /**
     * Accept legacy `phone` as an alias for `mobile_number`.
     */
    private function normalizeMobileAlias(mixed $record): mixed
    {
        if (! is_array($record)) {
            return $record;
        }

        if (! isset($record['mobile_number']) && isset($record['phone']) && filled($record['phone'])) {
            $record['mobile_number'] = $record['phone'];
        }

        unset($record['phone']);

        return $record;
    }
}
