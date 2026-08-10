<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class ErpNavigation
{
    /**
     * Top-level ERP apps (Odoo-style categories).
     *
     * @return list<array<string, mixed>>
     */
    public static function apps(): array
    {
        $user = Auth::user();
        $soon = fn (string $module, ?string $feature = null): string => route('coming-soon', array_filter([
            'module' => $module,
            'feature' => $feature,
        ]));

        return [
            [
                'key' => 'self-service',
                'label' => 'Self Service',
                'accent' => 'sky',
                'icon' => 'user',
                'href' => route('self-service.profile'),
                'available' => true,
                'active' => request()->routeIs('time-punch.*')
                    || request()->routeIs('self-service.*')
                    || (request()->routeIs('coming-soon') && request()->route('module') === 'self-service'),
                'menu' => [
                    ['label' => 'Time Punch', 'route' => 'time-punch.index', 'icon' => 'clock-3', 'active' => request()->routeIs('time-punch.*')],
                    ['label' => 'My Profile', 'route' => 'self-service.profile', 'icon' => 'id-card', 'active' => request()->routeIs('self-service.profile*')],
                    ['label' => 'My Schedule', 'href' => $soon('self-service', 'my-schedule'), 'icon' => 'calendar-range', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-schedule')],
                    ($user?->can('self-service.leave') || $user?->can('leave.request') || $user?->can('leave.view'))
                        ? ['label' => 'My Leave', 'route' => 'self-service.leave', 'icon' => 'calendar-off', 'active' => request()->routeIs('self-service.leave')]
                        : ['label' => 'My Leave', 'href' => $soon('self-service', 'my-leave'), 'icon' => 'calendar-off', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-leave')],
                    ['label' => 'My DTR', 'href' => $soon('self-service', 'my-dtr'), 'icon' => 'file-clock', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-dtr')],
                    ['label' => 'My Payslip', 'href' => $soon('self-service', 'my-payslip'), 'icon' => 'banknote', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-payslip')],
                ],
            ],
            [
                'key' => 'employees',
                'label' => 'Employees',
                'accent' => 'violet',
                'icon' => 'users',
                'href' => ($user?->can('employees.view') || $user?->can('employees.manage'))
                    ? route('employees.index')
                    : $soon('employees', 'directory'),
                'available' => (bool) ($user?->can('employees.view') || $user?->can('employees.manage')),
                'visible' => true,
                'active' => request()->routeIs('employees.*')
                    || (request()->routeIs('coming-soon') && request()->route('module') === 'employees'),
                'menu' => [
                    ($user?->can('employees.view') || $user?->can('employees.manage'))
                        ? ['label' => 'Employee Directory', 'route' => 'employees.index', 'icon' => 'users', 'active' => request()->routeIs('employees.index')]
                        : ['label' => 'Employee Directory', 'href' => $soon('employees', 'directory'), 'icon' => 'users', 'coming_soon' => true, 'active' => self::isSoon('employees', 'directory')],
                    ($user?->can('employees.view') || $user?->can('employees.manage'))
                        ? ['label' => 'Personal Data Sheet', 'route' => 'employees.index', 'icon' => 'id-card', 'active' => request()->routeIs('employees.show')]
                        : ['label' => 'Personal Data Sheet', 'href' => $soon('employees', 'pds'), 'icon' => 'id-card', 'coming_soon' => true, 'active' => self::isSoon('employees', 'pds')],
                ],
            ],
            [
                'key' => 'leave',
                'label' => 'Leave',
                'accent' => 'amber',
                'icon' => 'umbrella',
                'href' => ($user?->can('leave.view') || $user?->can('leave.request') || $user?->can('leave.approve'))
                    ? route('leave.requests')
                    : ($user?->can('leave.credits')
                        ? route('leave.credits')
                        : ($user?->can('leave.reports')
                            ? route('leave.reports')
                            : $soon('leave', 'requests'))),
                'available' => (bool) (
                    $user?->can('leave.view')
                    || $user?->can('leave.request')
                    || $user?->can('leave.approve')
                    || $user?->can('leave.credits')
                    || $user?->can('leave.reports')
                ),
                'visible' => true,
                'active' => request()->routeIs('leave.*')
                    || (request()->routeIs('coming-soon') && request()->route('module') === 'leave'),
                'menu' => array_values(array_filter([
                    ($user?->can('leave.view') || $user?->can('leave.request') || $user?->can('leave.approve'))
                        ? ['label' => 'Leave Requests', 'route' => 'leave.requests', 'icon' => 'calendar-off', 'active' => request()->routeIs('leave.requests*')]
                        : null,
                    ($user?->can('leave.approve') || $user?->can('leave.view'))
                        ? ['label' => 'Leave Approvals', 'route' => 'leave.approvals', 'icon' => 'user-check', 'active' => request()->routeIs('leave.approvals')]
                        : null,
                    ($user?->can('leave.credits') || $user?->can('leave.view'))
                        ? ['label' => 'Leave Credits', 'route' => 'leave.credits', 'icon' => 'coins', 'active' => request()->routeIs('leave.credits')]
                        : null,
                    ($user?->can('leave.credits') || $user?->can('leave.view'))
                        ? ['label' => 'Leave Card', 'route' => 'leave.card', 'icon' => 'book-text', 'active' => request()->routeIs('leave.card*')]
                        : null,
                    ($user?->can('leave.reports') || $user?->can('leave.view'))
                        ? ['label' => 'Leave Reports', 'route' => 'leave.reports', 'icon' => 'clipboard-list', 'active' => request()->routeIs('leave.reports')]
                        : null,
                ])),
            ],
            [
                'key' => 'scheduling',
                'label' => 'Schedule',
                'accent' => 'cyan',
                'icon' => 'calendar-range',
                'href' => $user?->can('schedule.view') ? route('schedule.dashboard') : $soon('scheduling', 'dashboard'),
                'available' => (bool) $user?->can('schedule.view'),
                'active' => request()->routeIs(
                    'schedule.dashboard',
                    'schedule.shift-codes',
                    'schedule.employees',
                    'schedule.rotation-groups',
                    'schedule.staffing-requirements',
                    'schedule.templates',
                    'schedule.print-settings',
                    'schedule.print'
                ) || (request()->routeIs('coming-soon') && request()->route('module') === 'scheduling'),
                'menu' => array_values(array_filter([
                    $user?->can('schedule.view')
                        ? ['label' => 'Dashboard', 'route' => 'schedule.dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('schedule.dashboard')]
                        : ['label' => 'Dashboard', 'href' => $soon('scheduling', 'dashboard'), 'icon' => 'layout-dashboard', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'dashboard')],
                    $user?->can('schedule.view') ? ['label' => 'Shift Codes', 'route' => 'schedule.shift-codes', 'icon' => 'clock-3', 'active' => request()->routeIs('schedule.shift-codes')] : null,
                    $user?->can('schedule.view') ? ['label' => 'Employee Settings', 'route' => 'schedule.employees', 'icon' => 'user-cog', 'active' => request()->routeIs('schedule.employees')] : null,
                    $user?->can('schedule.view') ? ['label' => 'Rotation Groups', 'route' => 'schedule.rotation-groups', 'icon' => 'refresh-cw', 'active' => request()->routeIs('schedule.rotation-groups')] : null,
                    $user?->can('schedule.view') ? ['label' => 'Staffing', 'route' => 'schedule.staffing-requirements', 'icon' => 'clipboard-list', 'active' => request()->routeIs('schedule.staffing-requirements')] : null,
                    $user?->can('schedule.view') ? ['label' => 'Templates', 'route' => 'schedule.templates', 'icon' => 'table-properties', 'active' => request()->routeIs('schedule.templates')] : null,
                    $user?->can('schedule.view') ? ['label' => 'Print Settings', 'route' => 'schedule.print-settings', 'icon' => 'printer', 'active' => request()->routeIs('schedule.print-settings')] : null,
                    ['label' => 'Units', 'href' => $soon('scheduling', 'units'), 'icon' => 'building', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'units')],
                    ['label' => 'Floaters', 'href' => $soon('scheduling', 'floaters'), 'icon' => 'refresh-cw', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'floaters')],
                    ['label' => 'On Call', 'href' => $soon('scheduling', 'on-call'), 'icon' => 'phone', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'on-call')],
                    ['label' => 'Shift Swaps', 'href' => $soon('scheduling', 'swaps'), 'icon' => 'arrow-left-right', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'swaps')],
                    ['label' => 'Duty Census', 'href' => $soon('scheduling', 'census'), 'icon' => 'chart-no-axes-column', 'coming_soon' => true, 'active' => self::isSoon('scheduling', 'census')],
                ])),
            ],
            [
                'key' => 'timekeeping',
                'label' => 'Timekeeping',
                'accent' => 'teal',
                'icon' => 'file-clock',
                'href' => $user?->can('timekeeping.view') ? route('payroll.dtr-encoding') : $soon('timekeeping', 'dtr'),
                'available' => (bool) $user?->can('timekeeping.view'),
                'visible' => (bool) $user?->can('timekeeping.view'),
                'active' => request()->routeIs(
                    'payroll.daily-attendance',
                    'payroll.attendance-report',
                    'payroll.dtr',
                    'payroll.dtr-encoding',
                    'payroll.dtr-correction-requests',
                    'payroll.dtr-correction-approvers',
                    'payroll.mra',
                    'payroll.holidays'
                ) || (request()->routeIs('coming-soon') && request()->route('module') === 'timekeeping'),
                'menu' => [
                    ['label' => 'Daily Attendance', 'route' => 'payroll.daily-attendance', 'icon' => 'calendar-check', 'active' => request()->routeIs('payroll.daily-attendance')],
                    ['label' => 'Attendance Report', 'route' => 'payroll.attendance-report', 'icon' => 'clipboard-list', 'active' => request()->routeIs('payroll.attendance-report')],
                    ['label' => 'DTR Encoding', 'route' => 'payroll.dtr-encoding', 'icon' => 'file-clock', 'active' => request()->routeIs('payroll.dtr', 'payroll.dtr-encoding')],
                    ['label' => 'DTR Corrections', 'route' => 'payroll.dtr-correction-requests', 'icon' => 'file-pen-line', 'active' => request()->routeIs('payroll.dtr-correction-requests')],
                    ['label' => 'DTR Approvers', 'route' => 'payroll.dtr-correction-approvers', 'icon' => 'user-check', 'active' => request()->routeIs('payroll.dtr-correction-approvers')],
                    ['label' => 'MRA', 'route' => 'payroll.mra', 'icon' => 'chart-no-axes-column', 'active' => request()->routeIs('payroll.mra')],
                    ['label' => 'Holidays', 'route' => 'payroll.holidays', 'icon' => 'calendar-check', 'active' => request()->routeIs('payroll.holidays')],
                ],
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'accent' => 'indigo',
                'icon' => 'wallet',
                'href' => $user?->can('payroll.view') ? route('payroll.generation.configuration') : $soon('payroll', 'generation'),
                'available' => (bool) $user?->can('payroll.view'),
                'visible' => (bool) $user?->can('payroll.view'),
                'active' => request()->routeIs(
                    'payroll.generation',
                    'payroll.generation.configuration',
                    'payroll.generation.hazard',
                    'payroll.generation.medicare',
                    'payroll.history',
                    'payroll.loan-imports',
                    'payroll.loan-references',
                    'payroll.additional-premiums',
                    'payroll.deduction-programs',
                    'payroll.statutory-contributions',
                    'payroll.compensations',
                    'payroll.adjustment-types'
                ) || (request()->routeIs('coming-soon') && request()->route('module') === 'payroll'),
                'menu' => [
                    ['label' => 'Payroll Generation', 'route' => 'payroll.generation.configuration', 'icon' => 'banknote', 'active' => request()->routeIs('payroll.generation', 'payroll.generation.configuration', 'payroll.generation.hazard', 'payroll.generation.medicare')],
                    ['label' => 'Payroll History', 'route' => 'payroll.history', 'icon' => 'history', 'active' => request()->routeIs('payroll.history')],
                    ['label' => 'Loan Due Imports', 'route' => 'payroll.loan-imports', 'icon' => 'upload', 'active' => request()->routeIs('payroll.loan-imports')],
                    ['label' => 'Loan References', 'route' => 'payroll.loan-references', 'icon' => 'files', 'active' => request()->routeIs('payroll.loan-references')],
                    ['label' => 'Additional Premiums', 'route' => 'payroll.additional-premiums', 'icon' => 'coins', 'active' => request()->routeIs('payroll.additional-premiums')],
                    ['label' => 'Deduction Programs', 'route' => 'payroll.deduction-programs', 'icon' => 'list-checks', 'active' => request()->routeIs('payroll.deduction-programs')],
                    ['label' => 'Mandatory Deductions', 'route' => 'payroll.statutory-contributions', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.statutory-contributions')],
                    ['label' => 'Compensation Rules', 'route' => 'payroll.compensations', 'icon' => 'coins', 'active' => request()->routeIs('payroll.compensations')],
                    ['label' => 'Adjustment Types', 'route' => 'payroll.adjustment-types', 'icon' => 'sliders', 'active' => request()->routeIs('payroll.adjustment-types')],
                ],
            ],
            [
                'key' => 'training',
                'label' => 'Training',
                'accent' => 'rose',
                'icon' => 'graduation-cap',
                'href' => $soon('training', 'tarf'),
                'available' => false,
                'active' => request()->routeIs('coming-soon') && request()->route('module') === 'training',
                'menu' => [
                    ['label' => 'TARF / Requests', 'href' => $soon('training', 'tarf'), 'icon' => 'graduation-cap', 'coming_soon' => true, 'active' => self::isSoon('training', 'tarf')],
                ],
            ],
            [
                'key' => 'performance',
                'label' => 'Performance',
                'accent' => 'orange',
                'icon' => 'award',
                'href' => $soon('performance', 'ipcr'),
                'available' => false,
                'active' => request()->routeIs('coming-soon') && request()->route('module') === 'performance',
                'menu' => [
                    ['label' => 'IPCR', 'href' => $soon('performance', 'ipcr'), 'icon' => 'award', 'coming_soon' => true, 'active' => self::isSoon('performance', 'ipcr')],
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'Settings',
                'accent' => 'slate',
                'icon' => 'settings',
                'href' => $user?->can('admin.users.view')
                    ? route('admin.user-accounts')
                    : ($user?->can('admin.roles.view')
                        ? route('admin.roles-permissions')
                        : ($user?->can('references.view')
                            ? route('schedule.user-manual')
                            : $soon('administration', 'access'))),
                'available' => (bool) ($user?->can('admin.users.view') || $user?->can('admin.roles.view') || $user?->can('references.view')),
                'visible' => (bool) ($user?->can('admin.users.view') || $user?->can('admin.roles.view') || $user?->can('references.view')),
                'active' => request()->routeIs('admin.*', 'schedule.employee-references', 'schedule.user-manual', 'payroll.user-manual', 'references.*')
                    || (request()->routeIs('coming-soon') && request()->route('module') === 'administration'),
                'menu' => array_values(array_filter([
                    $user?->can('admin.users.view') ? ['label' => 'User Accounts', 'route' => 'admin.user-accounts', 'icon' => 'users', 'active' => request()->routeIs('admin.user-accounts')] : null,
                    $user?->can('admin.roles.view') ? ['label' => 'Roles and Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'shield-check', 'active' => request()->routeIs('admin.roles-permissions')] : null,
                    $user?->can('references.view') ? ['label' => 'Employee References', 'route' => 'schedule.employee-references', 'icon' => 'database', 'active' => request()->routeIs('schedule.employee-references')] : null,
                    $user?->can('references.view') ? ['label' => 'Schedule Manual', 'route' => 'schedule.user-manual', 'icon' => 'book-text', 'active' => request()->routeIs('schedule.user-manual')] : null,
                    $user?->can('references.view') ? ['label' => 'Payroll Manual', 'route' => 'payroll.user-manual', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.user-manual')] : null,
                    $user?->can('references.view') ? ['label' => 'Roles Manual', 'route' => 'references.roles-permissions-manual', 'icon' => 'shield-check', 'active' => request()->routeIs('references.roles-permissions-manual')] : null,
                ])),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function visibleApps(): array
    {
        return array_values(array_filter(
            self::apps(),
            fn (array $app) => $app['visible'] ?? true
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function currentApp(): ?array
    {
        foreach (self::visibleApps() as $app) {
            if ($app['active'] ?? false) {
                return $app;
            }
        }

        return null;
    }

    public static function isSoon(string $module, ?string $feature = null): bool
    {
        return request()->routeIs('coming-soon')
            && request()->route('module') === $module
            && ($feature === null || request()->route('feature') === $feature);
    }

    public static function href(array $item): string
    {
        if (isset($item['route']) && Route::has($item['route'])) {
            return route($item['route']);
        }

        return $item['href'] ?? '#';
    }

    /**
     * @return array<string, string>
     */
    public static function icons(): array
    {
        return [
            'arrow-left-right' => 'M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4',
            'award' => 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12z M8.2 13.8 7 22l5-3 5 3-1.2-8.2',
            'banknote' => 'M6 18h12 M6 6h12 M6 6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2 M18 6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2 M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
            'book-text' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20 M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z M8 7h8 M8 11h8 M8 15h5',
            'building' => 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18M6 12h12M10 6h.01M14 6h.01M10 10h.01M14 10h.01M10 14h.01M14 14h.01M10 18h.01M14 18h.01',
            'calendar-check' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M9 16l2 2 4-5',
            'calendar-off' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M14.5 14.5l-5 5 M9.5 14.5l5 5',
            'calendar-range' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M8 14h3 M13 14h3 M8 18h8',
            'chart-no-axes-column' => 'M5 21V10 M12 21V3 M19 21v-7',
            'clipboard-list' => 'M9 5h6 M9 12h6 M9 16h6 M7 5h.01 M7 12h.01 M7 16h.01 M9 3h6l1 2h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2z',
            'clock-3' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 6v6h4',
            'coins' => 'M9 8c0 1.7-2.2 3-5 3s-5-1.3-5-3 2.2-3 5-3 5 1.3 5 3z M4 11v4c0 1.7 2.2 3 5 3s5-1.3 5-3v-4',
            'database' => 'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3 M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6 M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6',
            'file-clock' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M12 13v3l2 1',
            'file-pen-line' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M16 13l3 3',
            'files' => 'M15 2H6a2 2 0 0 0-2 2v13 M8 6h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
            'graduation-cap' => 'M22 10v6 M2 10l10-5 10 5-10 5z M6 12v5c3 3 9 3 12 0v-5',
            'grid' => 'M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z',
            'history' => 'M3 12a9 9 0 1 0 9-9 M12 7v5l3 3',
            'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
            'id-card' => 'M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z M8 11a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'layout-dashboard' => 'M3 3h8v8H3z M13 3h8v5h-8z M13 10h8v11h-8z M3 13h8v8H3z',
            'list-checks' => 'M10 6h10 M10 12h10 M10 18h10 M4 6l1.5 1.5L8 5 M4 12l1.5 1.5L8 11 M4 18l1.5 1.5L8 17',
            'phone' => 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.3a2 2 0 0 1 2.1-.4c.8.3 1.7.5 2.6.6a2 2 0 0 1 1.8 2.1z',
            'printer' => 'M6 9V2h12v7 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v8H6z',
            'refresh-cw' => 'M21 12a9 9 0 0 1-15.5 6.3L3 16 M3 21v-5h5 M3 12A9 9 0 0 1 18.5 5.7L21 8 M21 3v5h-5',
            'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
            'shield-check' => 'M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3z M9 12l2 2 4-4',
            'sliders' => 'M4 21v-7 M4 10V3 M12 21v-9 M12 8V3 M20 21v-5 M20 12V3 M2 14h4 M10 8h4 M18 16h4',
            'table-properties' => 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 9h18 M9 9v12',
            'umbrella' => 'M12 13v8 M8 21h8 M12 3a8 8 0 0 0-8 8h16a8 8 0 0 0-8-8z',
            'upload' => 'M12 3v12 M7 8l5-5 5 5 M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
            'user' => 'M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
            'user-check' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M16 11l2 2 4-4',
            'user-cog' => 'M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M2 21a8 8 0 0 1 12.2-6.8',
            'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.9 M16 3.1a4 4 0 0 1 0 7.8',
            'wallet' => 'M20 7V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V7 M16 14h.01',
        ];
    }
}
