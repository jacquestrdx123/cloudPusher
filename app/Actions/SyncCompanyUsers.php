<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncCompanyUsers
{
    /**
     * Declaratively reconcile a company's user membership against the payload.
     *
     * Users are matched (within the company) by their pivot external_id first,
     * then by global email, then by global phone. Unmatched records create a
     * new platform user. When $deleteMissing is true, members not present in
     * the payload are detached from the company (the global user is preserved).
     *
     * @param  array<int, array<string, mixed>>  $users
     * @return array{created: int, updated: int, unchanged: int, removed: int, skipped: array<int, array{email: string|null, external_id: string|null, reason: string}>}
     */
    public function handle(Company $company, array $users, bool $deleteMissing = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'skipped' => []];

        /** @var array<int, int> $matchedIds */
        $matchedIds = [];

        DB::transaction(function () use ($company, $users, $deleteMissing, &$summary, &$matchedIds): void {
            foreach ($users as $record) {
                $email = strtolower(trim((string) ($record['email'] ?? '')));
                $externalId = isset($record['external_id']) && filled($record['external_id'])
                    ? (string) $record['external_id']
                    : null;
                $phone = isset($record['phone']) && filled($record['phone'])
                    ? PhoneNumber::normalize((string) $record['phone'])
                    : null;

                if ($email === '') {
                    $summary['skipped'][] = [
                        'email' => null,
                        'external_id' => $externalId,
                        'reason' => 'An email address is required.',
                    ];

                    continue;
                }

                $user = $this->matchUser($company, $externalId, $email, $phone);

                $isNewUser = $user === null;

                if ($isNewUser) {
                    $user = User::query()->create([
                        'name' => (string) ($record['name'] ?? $email),
                        'email' => $email,
                        'phone' => $phone,
                        'locale' => $record['locale'] ?? null,
                        'password' => Hash::make(Str::random(40)),
                        'email_verified_at' => now(),
                    ]);
                }

                $matchedIds[] = $user->id;

                $wasMember = $company->users()->whereKey($user->id)->exists();
                $ownsProfile = $isNewUser || $wasMember;

                $profileChanged = false;

                if (! $isNewUser && $ownsProfile) {
                    $profileChanged = $this->applyProfile($user, $record, $email, $phone, $externalId, $summary);
                }

                $pivotChanged = $this->syncPivot($company, $user->id, $record, $externalId, $wasMember);

                if (! $wasMember) {
                    $summary['created']++;
                } elseif ($profileChanged || $pivotChanged) {
                    $summary['updated']++;
                } else {
                    $summary['unchanged']++;
                }
            }

            if ($deleteMissing) {
                $summary['removed'] = $this->pruneMissing($company, $matchedIds);
            }
        });

        return $summary;
    }

    private function matchUser(Company $company, ?string $externalId, string $email, ?string $phone): ?User
    {
        if ($externalId !== null) {
            $byExternal = $company->users()->wherePivot('external_id', $externalId)->first();

            if ($byExternal instanceof User) {
                return $byExternal;
            }
        }

        $byEmail = User::query()->where('email', $email)->first();

        if ($byEmail instanceof User) {
            return $byEmail;
        }

        if ($phone !== null) {
            return User::query()->where('phone', $phone)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{created: int, updated: int, unchanged: int, removed: int, skipped: array<int, array{email: string|null, external_id: string|null, reason: string}>}  $summary
     */
    private function applyProfile(User $user, array $record, string $email, ?string $phone, ?string $externalId, array &$summary): bool
    {
        if (array_key_exists('name', $record) && filled($record['name']) && $user->name !== $record['name']) {
            $user->name = (string) $record['name'];
        }

        if (array_key_exists('locale', $record) && $user->locale !== $record['locale']) {
            $user->locale = $record['locale'];
        }

        if ($user->email !== $email) {
            if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'external_id' => $externalId,
                    'reason' => 'Email already belongs to another user; profile email left unchanged.',
                ];
            } else {
                $user->email = $email;
            }
        }

        if ($phone !== null && $user->phone !== $phone) {
            if (User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'external_id' => $externalId,
                    'reason' => 'Phone already belongs to another user; profile phone left unchanged.',
                ];
            } else {
                $user->phone = $phone;
            }
        }

        if (! $user->isDirty()) {
            return false;
        }

        $user->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function syncPivot(Company $company, int $userId, array $record, ?string $externalId, bool $wasMember): bool
    {
        $attributes = [];

        if (array_key_exists('is_company_admin', $record) && $record['is_company_admin'] !== null) {
            $attributes['is_company_admin'] = (bool) $record['is_company_admin'];
        }

        if ($externalId !== null) {
            $attributes['external_id'] = $externalId;
        }

        if (! $wasMember) {
            $company->users()->attach($userId, $attributes + ['is_company_admin' => $attributes['is_company_admin'] ?? false]);

            return true;
        }

        if ($attributes === []) {
            return false;
        }

        /** @var CompanyUser|null $pivot */
        $pivot = $company->users()->whereKey($userId)->first()?->getRelationValue('pivot');

        $isDifferent = $pivot === null
            || (array_key_exists('is_company_admin', $attributes) && (bool) $pivot->is_company_admin !== $attributes['is_company_admin'])
            || (array_key_exists('external_id', $attributes) && $pivot->external_id !== $attributes['external_id']);

        if (! $isDifferent) {
            return false;
        }

        $company->users()->updateExistingPivot($userId, $attributes);

        return true;
    }

    /**
     * Detach members absent from the payload and clear their group memberships.
     *
     * @param  array<int, int>  $matchedIds
     */
    private function pruneMissing(Company $company, array $matchedIds): int
    {
        $stale = $company->users()
            ->when($matchedIds !== [], fn ($query) => $query->whereKeyNot($matchedIds))
            ->pluck('users.id')
            ->all();

        if ($stale === []) {
            return 0;
        }

        DB::table('group_user')
            ->whereIn('user_id', $stale)
            ->whereIn('user_group_id', $company->groups()->select('id'))
            ->delete();

        $company->users()->detach($stale);

        return count($stale);
    }
}
