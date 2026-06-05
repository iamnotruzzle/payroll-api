<?php

namespace App\Support\Rbac;

class LegacyHrisRoleMapper
{
    public const DEFAULT_SUPER_ADMIN_EMPLOYEE_IDS = [
        '001783',
    ];

    public const SYSTEM_ROLES = [
        'super-admin',
        'admin',
        'scheduler',
        'schedule-approver',
        'payroll-processor',
        'payroll-approver',
        'timekeeper',
        'employee',
    ];

    /**
     * Legacy HRIS levels are broad account tiers, while PIMS roles are closer to
     * payroll/timekeeping duties. Unknown values intentionally fall back to the
     * least privileged role instead of blocking the account entirely.
     */
    public function rolesFor(?int $userLevel, ?int $pimsRole, ?string $employeeId = null): array
    {
        if ($employeeId !== null && in_array($employeeId, self::DEFAULT_SUPER_ADMIN_EMPLOYEE_IDS, true)) {
            return ['super-admin'];
        }

        $roles = [
            ...$this->rolesFromUserLevel($userLevel),
            ...$this->rolesFromPimsRole($pimsRole),
        ];

        if (in_array('super-admin', $roles, true)) {
            return ['super-admin'];
        }

        if (in_array('admin', $roles, true)) {
            return ['admin'];
        }

        $roles = array_values(array_unique($roles));

        return $roles === [] ? ['employee'] : $roles;
    }

    public function unmappedValues(?int $userLevel, ?int $pimsRole): array
    {
        return [
            'user_level' => $userLevel !== null && ! in_array($userLevel, [1, 2, 3, 4, 5], true)
                ? $userLevel
                : null,
            'pims_role' => $pimsRole !== null && ! in_array($pimsRole, [1, 2, 3, 4, 5], true)
                ? $pimsRole
                : null,
        ];
    }

    private function rolesFromUserLevel(?int $userLevel): array
    {
        return match ($userLevel) {
            null => [],
            1 => ['super-admin'],
            2 => ['admin'],
            3 => ['scheduler', 'schedule-approver'],
            4 => ['scheduler'],
            5 => ['employee'],
            default => [],
        };
    }

    private function rolesFromPimsRole(?int $pimsRole): array
    {
        return match ($pimsRole) {
            null => [],
            1 => ['super-admin'],
            2 => ['admin'],
            3 => ['payroll-approver'],
            4 => ['payroll-processor', 'timekeeper'],
            5 => ['timekeeper'],
            default => [],
        };
    }
}
