<?php

namespace App\Services\Rbac;

use App\Models\Hris\UserAccount;
use App\Support\Rbac\LegacyHrisRoleMapper;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LegacyHrisRbacBackfill
{
    public function __construct(private readonly LegacyHrisRoleMapper $mapper) {}

    public function preview(bool $includeAccountsWithRoles = false): array
    {
        return $this->run(apply: false, includeAccountsWithRoles: $includeAccountsWithRoles);
    }

    public function apply(bool $includeAccountsWithRoles = false): array
    {
        return $this->run(apply: true, includeAccountsWithRoles: $includeAccountsWithRoles);
    }

    private function run(bool $apply, bool $includeAccountsWithRoles): array
    {
        $summary = [
            'scanned' => 0,
            'eligible' => 0,
            'skipped_existing_roles' => 0,
            'updated' => 0,
            'missing_roles' => [],
            'unmapped_user_levels' => [],
            'unmapped_pims_roles' => [],
            'assignments' => [],
        ];

        $query = UserAccount::query()
            ->with('roles')
            ->orderBy('userid');
        $existingRoleNames = $this->existingRoleNames();

        $query->chunkById(200, function (Collection $accounts) use (&$summary, $apply, $includeAccountsWithRoles, $existingRoleNames) {
            foreach ($accounts as $account) {
                $summary['scanned']++;

                if (! $includeAccountsWithRoles && $account->roles->isNotEmpty()) {
                    $summary['skipped_existing_roles']++;

                    continue;
                }

                $roles = $this->mapper->rolesFor($account->user_level, $account->pims_role, $account->emp_id);
                $missingRoles = array_values(array_diff($roles, $existingRoleNames));

                if ($missingRoles !== []) {
                    $summary['missing_roles'] = array_values(array_unique([
                        ...$summary['missing_roles'],
                        ...$missingRoles,
                    ]));

                    continue;
                }

                $unmapped = $this->mapper->unmappedValues($account->user_level, $account->pims_role);

                if ($unmapped['user_level'] !== null) {
                    $summary['unmapped_user_levels'][$unmapped['user_level']] = ($summary['unmapped_user_levels'][$unmapped['user_level']] ?? 0) + 1;
                }

                if ($unmapped['pims_role'] !== null) {
                    $summary['unmapped_pims_roles'][$unmapped['pims_role']] = ($summary['unmapped_pims_roles'][$unmapped['pims_role']] ?? 0) + 1;
                }

                $summary['eligible']++;
                $summary['assignments'][] = [
                    'userid' => $account->userid,
                    'emp_id' => $account->emp_id,
                    'user_level' => $account->user_level,
                    'pims_role' => $account->pims_role,
                    'roles' => $roles,
                ];

                if ($apply) {
                    $account->assignRole($roles);
                    $summary['updated']++;
                }
            }
        }, 'userid', 'userid');

        if ($apply && $summary['updated'] > 0) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        ksort($summary['unmapped_user_levels']);
        ksort($summary['unmapped_pims_roles']);

        return $summary;
    }

    private function existingRoleNames(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', LegacyHrisRoleMapper::SYSTEM_ROLES)
            ->pluck('name')
            ->all();
    }
}
