<?php

namespace Database\Seeders;

use App\Models\Hris\UserAccount;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RBACSeeder extends Seeder
{
    private const GUARD = 'web';

    private const DEFAULT_SUPER_ADMIN_EMPLOYEE_IDS = [
        '001783',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::findOrCreate($name, self::GUARD);
            }
        }

        foreach ($this->roles() as $name => $definition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => self::GUARD],
                [
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            $role->syncPermissions($definition['permissions']);
        }

        $this->assignInitialAdminRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function groupedPermissions(): array
    {
        return (new self())->permissions();
    }

    private function permissions(): array
    {
        return [
            'Administration' => [
                'admin.users.view' => 'View user accounts',
                'admin.users.manage' => 'Manage user accounts and assigned roles',
                'admin.roles.view' => 'View roles and permissions',
                'admin.roles.manage' => 'Manage roles and permissions',
            ],
            'Scheduling' => [
                'schedule.view' => 'Access scheduling workspace',
                'schedule.manage' => 'Manage schedule setup and monthly schedules',
                'schedule.approve' => 'Review, approve, and lock schedules',
            ],
            'Payroll' => [
                'payroll.view' => 'Access payroll workspace',
                'payroll.configure' => 'Manage payroll configuration and references',
                'payroll.generate' => 'Generate payroll runs',
                'payroll.approve' => 'Review and finalize payroll outputs',
            ],
            'Timekeeping' => [
                'timekeeping.view' => 'Access DTR and timekeeping workspace',
                'timekeeping.manage' => 'Encode DTR data and manage labels',
                'timekeeping.approve' => 'Approve DTR correction requests',
            ],
            'References' => [
                'references.view' => 'View manuals and employee references',
                'references.manage' => 'Sync and manage employee references',
            ],
        ];
    }

    private function roles(): array
    {
        $all = collect($this->permissions())->flatMap(fn (array $permissions) => array_keys($permissions))->values()->all();

        return [
            'super-admin' => [
                'display_name' => 'Super Administrator',
                'description' => 'Full access to all application areas and RBAC administration.',
                'permissions' => $all,
            ],
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Operational administrator with access to user, schedule, payroll, and timekeeping tools.',
                'permissions' => [
                    'admin.users.view',
                    'admin.users.manage',
                    'admin.roles.view',
                    'schedule.view',
                    'schedule.manage',
                    'schedule.approve',
                    'payroll.view',
                    'payroll.configure',
                    'payroll.generate',
                    'payroll.approve',
                    'timekeeping.view',
                    'timekeeping.manage',
                    'timekeeping.approve',
                    'references.view',
                    'references.manage',
                ],
            ],
            'scheduler' => [
                'display_name' => 'Scheduler',
                'description' => 'Builds and maintains schedules and schedule references.',
                'permissions' => [
                    'schedule.view',
                    'schedule.manage',
                    'references.view',
                    'references.manage',
                ],
            ],
            'schedule-approver' => [
                'display_name' => 'Schedule Approver',
                'description' => 'Reviews, approves, and locks monthly schedules.',
                'permissions' => [
                    'schedule.view',
                    'schedule.approve',
                    'references.view',
                ],
            ],
            'payroll-processor' => [
                'display_name' => 'Payroll Processor',
                'description' => 'Configures payroll data and generates payroll runs.',
                'permissions' => [
                    'payroll.view',
                    'payroll.configure',
                    'payroll.generate',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'payroll-approver' => [
                'display_name' => 'Payroll Approver',
                'description' => 'Reviews payroll outputs and payroll history.',
                'permissions' => [
                    'payroll.view',
                    'payroll.approve',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'timekeeper' => [
                'display_name' => 'Timekeeper',
                'description' => 'Encodes DTR data and manages timekeeping corrections.',
                'permissions' => [
                    'timekeeping.view',
                    'timekeeping.manage',
                    'references.view',
                ],
            ],
            'employee' => [
                'display_name' => 'Employee',
                'description' => 'Basic authenticated access to reference and help pages.',
                'permissions' => [
                    'references.view',
                ],
            ],
        ];
    }

    private function assignInitialAdminRoles(): void
    {
        UserAccount::query()
            ->whereIn('emp_id', self::DEFAULT_SUPER_ADMIN_EMPLOYEE_IDS)
            ->get()
            ->each(fn (UserAccount $account) => $account->assignRole('super-admin'));

        $hasSuperAdmin = UserAccount::role('super-admin')->exists();

        if (! $hasSuperAdmin) {
            UserAccount::query()
                ->where(function ($query) {
                    $query->where('user_level', '<=', 1)
                        ->orWhere('pims_role', '<=', 1);
                })
                ->orderBy('userid')
                ->limit(3)
                ->get()
                ->each(fn (UserAccount $account) => $account->assignRole('super-admin'));
        }

        UserAccount::query()
            ->where(function ($query) {
                $query->where('user_level', 2)
                    ->orWhere('pims_role', 2);
            })
            ->whereDoesntHave('roles')
            ->limit(25)
            ->get()
            ->each(fn (UserAccount $account) => $account->assignRole('admin'));
    }
}
