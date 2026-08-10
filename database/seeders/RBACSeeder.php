<?php

namespace Database\Seeders;

use App\Models\Hris\UserAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\LegacyHrisRoleMapper;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RBACSeeder extends Seeder
{
    private const GUARD = 'web';

    private const DESIGNATED_USER_ROLE_ASSIGNMENTS = [
        '001783' => ['super-admin'],
        '000720' => ['hr-payroll'],
        '000825' => ['hr-payroll'],
        '001866' => ['hr-payroll'],
        '001555' => ['hr-payroll'],
        '000035' => ['hr-payroll'],
        '000822' => ['hr-payroll'],
        '002039' => ['accounting-payroll'],
        '002205' => ['accounting-payroll'],
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
        $this->assignDesignatedUserRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function groupedPermissions(): array
    {
        return (new self)->permissions();
    }

    public static function roleDefinitions(): array
    {
        return (new self)->roles();
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
            'Self Service' => [
                'self-service.access' => 'Access self-service workspace',
                'self-service.profile' => 'View and update own profile',
                'self-service.leave' => 'File and track own leave',
                'self-service.schedule' => 'View own schedule',
                'self-service.payslip' => 'View own payslips',
                'self-service.dtr' => 'View own DTR / time punch',
            ],
            'Employees' => [
                'employees.view' => 'View employee directory and profiles',
                'employees.manage' => 'Create and update employee / PDS records',
                'employees.activate' => 'Activate or deactivate employees',
            ],
            'Leave' => [
                'leave.view' => 'View leave requests and leave cards',
                'leave.request' => 'Create leave requests for others',
                'leave.approve' => 'Approve or disapprove leave requests',
                'leave.credits' => 'Maintain leave credits',
                'leave.reports' => 'Run leave reports',
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
                'payroll.generation.hr' => 'Edit HR-owned payroll generation steps',
                'payroll.generation.accounting' => 'Edit Accounting-owned payroll generation fields and steps',
            ],
            'Timekeeping' => [
                'timekeeping.view' => 'Access DTR and timekeeping workspace',
                'timekeeping.manage' => 'Encode DTR data and manage labels',
                'timekeeping.approve' => 'Approve DTR correction requests',
            ],
            'Training' => [
                'training.view' => 'View training / TARF workspace',
                'training.manage' => 'Manage training requests and records',
                'training.approve' => 'Approve training requests',
            ],
            'Performance' => [
                'performance.view' => 'View IPCR / performance workspace',
                'performance.manage' => 'Manage IPCR targets and ratings',
                'performance.approve' => 'Calibrate or approve performance ratings',
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
                    'self-service.access',
                    'self-service.profile',
                    'self-service.leave',
                    'self-service.schedule',
                    'self-service.payslip',
                    'self-service.dtr',
                    'employees.view',
                    'employees.manage',
                    'employees.activate',
                    'leave.view',
                    'leave.request',
                    'leave.approve',
                    'leave.credits',
                    'leave.reports',
                    'schedule.view',
                    'schedule.manage',
                    'schedule.approve',
                    'payroll.view',
                    'payroll.configure',
                    'payroll.generate',
                    'payroll.approve',
                    'payroll.generation.hr',
                    'payroll.generation.accounting',
                    'timekeeping.view',
                    'timekeeping.manage',
                    'timekeeping.approve',
                    'training.view',
                    'training.manage',
                    'training.approve',
                    'performance.view',
                    'performance.manage',
                    'performance.approve',
                    'references.view',
                    'references.manage',
                ],
            ],
            'hr-officer' => [
                'display_name' => 'HR Officer',
                'description' => 'Manages employees, leave, and HR self-service support.',
                'permissions' => [
                    'self-service.access',
                    'employees.view',
                    'employees.manage',
                    'employees.activate',
                    'leave.view',
                    'leave.request',
                    'leave.approve',
                    'leave.credits',
                    'leave.reports',
                    'training.view',
                    'training.manage',
                    'performance.view',
                    'performance.manage',
                    'references.view',
                ],
            ],
            'scheduler' => [
                'display_name' => 'Scheduler',
                'description' => 'Builds and maintains schedules and schedule references.',
                'permissions' => [
                    'self-service.access',
                    'self-service.schedule',
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
                    'self-service.access',
                    'self-service.schedule',
                    'schedule.view',
                    'schedule.approve',
                    'references.view',
                ],
            ],
            'hr-payroll' => [
                'display_name' => 'HR Payroll',
                'description' => 'HR payroll staff who own payroll generation steps except Accounting-only fields.',
                'permissions' => [
                    'self-service.access',
                    'employees.view',
                    'leave.view',
                    'payroll.view',
                    'payroll.configure',
                    'payroll.generate',
                    'payroll.generation.hr',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'accounting-payroll' => [
                'display_name' => 'Accounting Payroll',
                'description' => 'Accounting staff who maintain TEV and tax/review payroll generation steps.',
                'permissions' => [
                    'self-service.access',
                    'payroll.view',
                    'payroll.approve',
                    'payroll.generation.accounting',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'payroll-processor' => [
                'display_name' => 'Payroll Processor',
                'description' => 'Configures payroll data and generates payroll runs.',
                'permissions' => [
                    'self-service.access',
                    'payroll.view',
                    'payroll.configure',
                    'payroll.generate',
                    'payroll.generation.hr',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'payroll-approver' => [
                'display_name' => 'Payroll Approver',
                'description' => 'Reviews payroll outputs and payroll history.',
                'permissions' => [
                    'self-service.access',
                    'payroll.view',
                    'payroll.approve',
                    'payroll.generation.accounting',
                    'timekeeping.view',
                    'references.view',
                ],
            ],
            'timekeeper' => [
                'display_name' => 'Timekeeper',
                'description' => 'Encodes DTR data and manages timekeeping corrections.',
                'permissions' => [
                    'self-service.access',
                    'self-service.dtr',
                    'timekeeping.view',
                    'timekeeping.manage',
                    'references.view',
                ],
            ],
            'employee' => [
                'display_name' => 'Employee',
                'description' => 'Self-service access for profile, leave, schedule, payslip, and help.',
                'permissions' => [
                    'self-service.access',
                    'self-service.profile',
                    'self-service.leave',
                    'self-service.schedule',
                    'self-service.payslip',
                    'self-service.dtr',
                    'references.view',
                ],
            ],
        ];
    }

    private function assignInitialAdminRoles(): void
    {
        UserAccount::query()
            ->whereIn('emp_id', LegacyHrisRoleMapper::DEFAULT_SUPER_ADMIN_EMPLOYEE_IDS)
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

    private function assignDesignatedUserRoles(): void
    {
        foreach (self::DESIGNATED_USER_ROLE_ASSIGNMENTS as $employeeId => $roles) {
            UserAccount::query()
                ->where('emp_id', $employeeId)
                ->get()
                ->each(fn (UserAccount $account) => $account->assignRole($roles));
        }
    }
}
