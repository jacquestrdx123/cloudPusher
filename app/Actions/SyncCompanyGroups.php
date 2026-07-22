<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\UserGroup;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncCompanyGroups
{
    /**
     * Declaratively reconcile a company's user groups against the payload.
     *
     * Groups are matched (within the company) by external_id first, then by
     * slug. When a group record includes a "members" list, that list becomes
     * the group's authoritative membership (referencing users that already
     * belong to the company). When $deleteMissing is true, groups absent from
     * the payload are deleted.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{created: int, updated: int, unchanged: int, removed: int, members_synced: int, skipped: array<int, array{group: string|null, member?: string, reason: string}>}
     */
    public function handle(Company $company, array $groups, bool $deleteMissing = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'members_synced' => 0, 'skipped' => []];

        /** @var array<int, int> $matchedIds */
        $matchedIds = [];

        DB::transaction(function () use ($company, $groups, $deleteMissing, &$summary, &$matchedIds): void {
            $memberIndex = $this->buildMemberIndex($company);

            foreach ($groups as $record) {
                $externalId = isset($record['external_id']) && filled($record['external_id'])
                    ? (string) $record['external_id']
                    : null;
                $name = isset($record['name']) && filled($record['name']) ? (string) $record['name'] : null;
                $requestedSlug = isset($record['slug']) && filled($record['slug']) ? Str::slug((string) $record['slug']) : null;

                $group = $this->matchGroup($company, $externalId, $requestedSlug);

                if ($group === null && $name === null && $requestedSlug === null) {
                    $summary['skipped'][] = [
                        'group' => $externalId,
                        'reason' => 'A group name or slug is required to create a group.',
                    ];

                    continue;
                }

                $isNew = $group === null;

                if ($isNew) {
                    $group = new UserGroup(['company_id' => $company->id]);
                }

                $slug = $requestedSlug
                    ?? ($group->slug ?: Str::slug((string) ($name ?? $externalId)));
                $slug = $this->uniqueSlug($company, $slug, $group->exists ? $group->id : null);

                $group->fill([
                    'name' => $name ?? $group->name ?? Str::headline($slug),
                    'slug' => $slug,
                    'external_id' => $externalId ?? $group->external_id,
                ]);

                $changed = $isNew || $group->isDirty();

                $group->save();
                $matchedIds[] = $group->id;

                if (array_key_exists('members', $record) && is_array($record['members'])) {
                    $membersChanged = $this->syncMembers($group, $record['members'], $memberIndex, $externalId, $summary);
                    $changed = $changed || $membersChanged;
                }

                if ($isNew) {
                    $summary['created']++;
                } elseif ($changed) {
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

    private function matchGroup(Company $company, ?string $externalId, ?string $slug): ?UserGroup
    {
        if ($externalId !== null) {
            $byExternal = $company->groups()->where('external_id', $externalId)->first();

            if ($byExternal instanceof UserGroup) {
                return $byExternal;
            }
        }

        if ($slug !== null) {
            return $company->groups()->where('slug', $slug)->first();
        }

        return null;
    }

    private function uniqueSlug(Company $company, string $base, ?int $ignoreId): string
    {
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while ($company->groups()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Build lookup maps of the company's members keyed by external_id, mobile
     * number, and email so member references resolve without per-row queries.
     *
     * @return array{external: array<string, int>, mobile: array<string, int>, email: array<string, int>}
     */
    private function buildMemberIndex(Company $company): array
    {
        $index = ['external' => [], 'mobile' => [], 'email' => []];

        foreach ($company->users()->get() as $user) {
            $index['email'][strtolower($user->email)] = $user->id;

            if ($user->phone !== null) {
                $index['mobile'][$user->phone] = $user->id;
            }

            $externalId = $user->getRelationValue('pivot')?->external_id;

            if (is_string($externalId) && $externalId !== '') {
                $index['external'][$externalId] = $user->id;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, mixed>  $members
     * @param  array{external: array<string, int>, mobile: array<string, int>, email: array<string, int>}  $index
     * @param  array{created: int, updated: int, unchanged: int, removed: int, members_synced: int, skipped: array<int, array{group: string|null, member?: string, reason: string}>}  $summary
     */
    private function syncMembers(UserGroup $group, array $members, array $index, ?string $groupRef, array &$summary): bool
    {
        $userIds = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $userId = $this->resolveMember($member, $index);

            if ($userId === null) {
                $summary['skipped'][] = [
                    'group' => $groupRef ?? $group->slug,
                    'member' => (string) ($member['external_id'] ?? $member['mobile_number'] ?? $member['email'] ?? $member['phone'] ?? ''),
                    'reason' => 'Member is not a user of this company; sync the user first.',
                ];

                continue;
            }

            $userIds[$userId] = $userId;
        }

        $summary['members_synced'] += count($userIds);

        $changes = $group->users()->sync(array_values($userIds));

        return $changes['attached'] !== [] || $changes['detached'] !== [];
    }

    /**
     * @param  array<string, mixed>  $member
     * @param  array{external: array<string, int>, mobile: array<string, int>, email: array<string, int>}  $index
     */
    private function resolveMember(array $member, array $index): ?int
    {
        if (isset($member['external_id']) && filled($member['external_id'])) {
            $id = $index['external'][(string) $member['external_id']] ?? null;

            if ($id !== null) {
                return $id;
            }
        }

        $rawMobile = $member['mobile_number'] ?? $member['phone'] ?? null;

        if (filled($rawMobile)) {
            $id = $index['mobile'][PhoneNumber::normalize((string) $rawMobile)] ?? null;

            if ($id !== null) {
                return $id;
            }
        }

        if (isset($member['email']) && filled($member['email'])) {
            return $index['email'][strtolower(trim((string) $member['email']))] ?? null;
        }

        return null;
    }

    /**
     * @param  array<int, int>  $matchedIds
     */
    private function pruneMissing(Company $company, array $matchedIds): int
    {
        return $company->groups()
            ->when($matchedIds !== [], fn ($query) => $query->whereKeyNot($matchedIds))
            ->get()
            ->each(fn (UserGroup $group) => $group->delete())
            ->count();
    }
}
