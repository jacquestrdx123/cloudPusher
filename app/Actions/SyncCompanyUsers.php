<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncCompanyUsers
{
    /**
     * Declaratively reconcile a company's user membership against the payload.
     *
     * Users are matched (within the company) by their pivot external_id first,
     * then by global mobile number, then by global email. Unmatched records
     * create a new platform user. When $deleteMissing is true, members not
     * present in the payload are detached from the company (the global user is
     * preserved).
     *
     * @param  array<int, array<string, mixed>>  $users
     * @return array{created: int, updated: int, unchanged: int, removed: int, skipped: array<int, array{email: string|null, mobile_number: string|null, external_id: string|null, reason: string}>}
     */
    public function handle(Company $company, array $users, bool $deleteMissing = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'skipped' => []];

        /** @var array<int, int> $matchedIds */
        $matchedIds = [];

        DB::transaction(function () use ($company, $users, $deleteMissing, &$summary, &$matchedIds): void {
            foreach ($users as $record) {
                $emailRaw = strtolower(trim((string) ($record['email'] ?? '')));
                $emailProvided = $emailRaw !== '';
                $externalId = isset($record['external_id']) && filled($record['external_id'])
                    ? (string) $record['external_id']
                    : null;
                $mobileNumber = $this->resolveMobileNumber($record);

                if ($mobileNumber === null) {
                    $summary['skipped'][] = [
                        'email' => $emailProvided ? $emailRaw : null,
                        'mobile_number' => null,
                        'external_id' => $externalId,
                        'reason' => 'A valid mobile_number is required.',
                    ];

                    continue;
                }

                $email = $emailProvided ? $emailRaw : $this->placeholderEmail($mobileNumber);

                $user = $this->matchUser(
                    $company,
                    $externalId,
                    $mobileNumber,
                    $emailProvided ? $emailRaw : null,
                );

                $isNewUser = $user === null;

                if ($isNewUser) {
                    try {
                        $user = User::query()->create([
                            'name' => (string) ($record['name'] ?? $mobileNumber),
                            'email' => $email,
                            'phone' => $mobileNumber,
                            'locale' => $record['locale'] ?? null,
                            'password' => Hash::make(Str::random(40)),
                            'email_verified_at' => now(),
                        ]);
                    } catch (UniqueConstraintViolationException $exception) {
                        // Race / missed match: fall back to associating the existing user.
                        $user = $this->matchUser(
                            $company,
                            $externalId,
                            $mobileNumber,
                            $emailProvided ? $emailRaw : null,
                        );

                        if (! $user instanceof User) {
                            throw $exception;
                        }

                        $isNewUser = false;
                    }
                }

                $matchedIds[] = $user->id;

                $wasMember = $company->users()->whereKey($user->id)->exists();
                $ownsProfile = $isNewUser || $wasMember;

                $profileChanged = false;

                if (! $isNewUser && $ownsProfile) {
                    $profileChanged = $this->applyProfile($user, $record, $email, $mobileNumber, $externalId, $summary);
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

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveMobileNumber(array $record): ?string
    {
        $raw = $record['mobile_number'] ?? $record['phone'] ?? null;

        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        if (! filled($raw)) {
            return null;
        }

        $normalized = PhoneNumber::normalize((string) $raw);

        return $normalized !== '' ? $normalized : null;
    }

    private function placeholderEmail(string $mobileNumber): string
    {
        $digits = preg_replace('/\D+/', '', $mobileNumber) ?: Str::lower(Str::random(12));

        return "mobile+{$digits}@users.local";
    }

    private function matchUser(Company $company, ?string $externalId, string $mobileNumber, ?string $email): ?User
    {
        if ($externalId !== null) {
            $byExternal = $company->users()->wherePivot('external_id', $externalId)->first();

            if ($byExternal instanceof User) {
                return $byExternal;
            }
        }

        $byMobile = User::query()->where('phone', $mobileNumber)->first();

        if ($byMobile instanceof User) {
            return $byMobile;
        }

        if ($email === null) {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{created: int, updated: int, unchanged: int, removed: int, skipped: array<int, array{email: string|null, mobile_number: string|null, external_id: string|null, reason: string}>}  $summary
     */
    private function applyProfile(User $user, array $record, string $email, string $mobileNumber, ?string $externalId, array &$summary): bool
    {
        if (array_key_exists('name', $record) && filled($record['name']) && $user->name !== $record['name']) {
            $user->name = (string) $record['name'];
        }

        if (array_key_exists('locale', $record) && $user->locale !== $record['locale']) {
            $user->locale = $record['locale'];
        }

        if ($user->phone !== $mobileNumber) {
            if (User::query()->where('phone', $mobileNumber)->whereKeyNot($user->id)->exists()) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'mobile_number' => $mobileNumber,
                    'external_id' => $externalId,
                    'reason' => 'Mobile number already belongs to another user; profile mobile left unchanged.',
                ];
            } else {
                $user->phone = $mobileNumber;
            }
        }

        $emailProvided = array_key_exists('email', $record) && filled($record['email']);

        if ($emailProvided && $user->email !== $email) {
            if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                $summary['skipped'][] = [
                    'email' => $email,
                    'mobile_number' => $mobileNumber,
                    'external_id' => $externalId,
                    'reason' => 'Email already belongs to another user; profile email left unchanged.',
                ];
            } else {
                $user->email = $email;
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
