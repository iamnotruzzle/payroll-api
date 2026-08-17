<?php

namespace App\Support;

use App\Services\Schedule\ScheduleScopeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class ErpNavigation
{
    /**
     * @param  list<array{label?: string|null, items: list<array<string, mixed>|null>}>  $sections
     * @return list<array{label: string|null, items: list<array<string, mixed>>}>
     */
    public static function menuSections(array $sections): array
    {
        $normalized = [];

        foreach ($sections as $section) {
            $items = array_values(array_filter($section['items'] ?? []));
            if ($items === []) {
                continue;
            }

            $normalized[] = [
                'label' => $section['label'] ?? null,
                'items' => $items,
            ];
        }

        return $normalized;
    }

    /**
     * Flat menu items (legacy helpers / tests).
     *
     * @return list<array<string, mixed>>
     */
    public static function flatMenu(array $app): array
    {
        $items = [];
        foreach ($app['menu_sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

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
        $scheduleScope = app(ScheduleScopeService::class);
        $userDepartmentId = $user?->employee?->department_id;
        $scheduleProfile = $scheduleScope->profileForDepartment($userDepartmentId);
        $isCnoSchedule = $scheduleScope->isCnoDepartment($userDepartmentId);
        $unitsNavLabel = $scheduleScope->unitNoun($userDepartmentId, true);

        // App order: employee hub → people ops → attendance/pay → learning → admin
        return [
            [
                'key' => 'self-service',
                'label' => 'Self Service',
                'description' => 'Your profile, DTR, schedule, leave, training, and payslips.',
                'launcher_group' => 'workspace',
                'accent' => 'sky',
                'icon' => 'user',
                'href' => ($user?->can('self-service.profile') || $user?->can('self-service.access'))
                    ? route('self-service.profile')
                    : (($user?->can('self-service.dtr') || $user?->can('self-service.payslip'))
                        ? route($user?->can('self-service.dtr') ? 'self-service.dtr' : 'self-service.payslip')
                        : (($user?->can('self-service.leave') || $user?->can('leave.request') || $user?->can('leave.view'))
                            ? route('self-service.leave')
                            : $soon('self-service', 'my-profile'))),
                'available' => true,
                'active' => request()->routeIs('time-punch.*')
                    || request()->routeIs('self-service.*')
                    || (request()->routeIs('coming-soon') && request()->route('module') === 'self-service'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Profile',
                        'items' => [
                            ($user?->can('self-service.profile') || $user?->can('self-service.access'))
                                ? ['label' => 'My Profile', 'route' => 'self-service.profile', 'icon' => 'id-card', 'active' => request()->routeIs('self-service.profile*')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Time & leave',
                        'items' => [
                            ($user?->can('self-service.leave') || $user?->can('leave.request') || $user?->can('leave.view'))
                                ? ['label' => 'My Leave', 'route' => 'self-service.leave', 'icon' => 'calendar-off', 'active' => request()->routeIs('self-service.leave')]
                                : ['label' => 'My Leave', 'href' => $soon('self-service', 'my-leave'), 'icon' => 'calendar-off', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-leave')],
                            ($user?->can('self-service.dtr') || $user?->can('self-service.access'))
                                ? ['label' => 'My DTR', 'route' => 'self-service.dtr', 'icon' => 'file-clock', 'active' => request()->routeIs('self-service.dtr*')]
                                : ['label' => 'My DTR', 'href' => $soon('self-service', 'my-dtr'), 'icon' => 'file-clock', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-dtr')],
                            ($user?->can('self-service.dtr') || $user?->can('self-service.access'))
                                ? ['label' => 'Time Punch', 'route' => 'time-punch.index', 'icon' => 'clock-3', 'active' => request()->routeIs('time-punch.*')]
                                : null,
                            ($user?->can('self-service.schedule') || $user?->can('self-service.access'))
                                ? ['label' => 'My Schedule', 'route' => 'self-service.schedule', 'icon' => 'calendar-range', 'active' => request()->routeIs('self-service.schedule')]
                                : ['label' => 'My Schedule', 'href' => $soon('self-service', 'my-schedule'), 'icon' => 'calendar-range', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-schedule')],
                            (($user?->can('self-service.schedule') || $user?->can('self-service.access')) && $scheduleProfile->uses_swaps)
                                ? ['label' => 'My Shift Swaps', 'route' => 'self-service.swaps', 'icon' => 'arrow-left-right', 'active' => request()->routeIs('self-service.swaps')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Pay',
                        'items' => [
                            ($user?->can('self-service.payslip') || $user?->can('self-service.access'))
                                ? ['label' => 'My Payslip', 'route' => 'self-service.payslip', 'icon' => 'banknote', 'active' => request()->routeIs('self-service.payslip*')]
                                : ['label' => 'My Payslip', 'href' => $soon('self-service', 'my-payslip'), 'icon' => 'banknote', 'coming_soon' => true, 'active' => self::isSoon('self-service', 'my-payslip')],
                        ],
                    ],
                    [
                        'label' => 'Development',
                        'items' => [
                            ($user?->can('self-service.training') || $user?->can('training.manage') || $user?->can('training.view'))
                                ? ['label' => 'My Training', 'route' => 'self-service.training', 'icon' => 'graduation-cap', 'active' => request()->routeIs('self-service.training')]
                                : null,
                            ($user?->can('self-service.ipcr') || $user?->can('performance.view') || $user?->can('performance.manage'))
                                ? ['label' => 'My IPCR', 'route' => 'self-service.ipcr', 'icon' => 'award', 'active' => request()->routeIs('self-service.ipcr')]
                                : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'employees',
                'label' => 'Employees',
                'description' => 'Employee directory, records, plantilla, and organization data.',
                'launcher_group' => 'operations',
                'accent' => 'violet',
                'icon' => 'users',
                'href' => route('employees.index'),
                'available' => (bool) ($user?->can('employees.view') || $user?->can('employees.manage')),
                'visible' => (bool) ($user?->can('employees.view') || $user?->can('employees.manage')),
                'active' => request()->routeIs('employees.index', 'employees.create', 'employees.show', 'employees.print', 'employees.documents.download')
                    || ($user?->can('employees.manage') && request()->routeIs(
                        'setup.organization',
                        'setup.positions',
                        'setup.salary-schedules',
                        'setup.plantilla',
                        'employees.masterlist-import'
                    )),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Directory',
                        'items' => [
                            ['label' => 'Employee Directory', 'route' => 'employees.index', 'icon' => 'users', 'active' => request()->routeIs('employees.index')],
                            ['label' => 'Personal Data Sheet', 'route' => 'employees.index', 'icon' => 'id-card', 'active' => request()->routeIs('employees.show')],
                        ],
                    ],
                    [
                        'label' => 'HRIS Setup',
                        'items' => $user?->can('employees.manage') ? [
                            ['label' => 'Organization', 'route' => 'setup.organization', 'icon' => 'building', 'active' => request()->routeIs('setup.organization')],
                            ['label' => 'Positions', 'route' => 'setup.positions', 'icon' => 'id-card', 'active' => request()->routeIs('setup.positions')],
                            ['label' => 'Salary Schedules', 'route' => 'setup.salary-schedules', 'icon' => 'coins', 'active' => request()->routeIs('setup.salary-schedules')],
                            ['label' => 'Plantilla Registry', 'route' => 'setup.plantilla', 'icon' => 'clipboard-list', 'active' => request()->routeIs('setup.plantilla')],
                            ['label' => 'Import Masterlist', 'route' => 'employees.masterlist-import', 'icon' => 'upload', 'active' => request()->routeIs('employees.masterlist-import')],
                        ] : [],
                    ],
                ]),
            ],
            [
                'key' => 'leave',
                'label' => 'Leave',
                'description' => 'Requests, approvals, credits, leave cards, and reports.',
                'launcher_group' => 'operations',
                'accent' => 'amber',
                'icon' => 'umbrella',
                'href' => ($user?->can('leave.view') || $user?->can('leave.request') || $user?->can('leave.approve'))
                    ? route('leave.requests')
                    : ($user?->can('leave.credits')
                        ? route('leave.credits')
                        : route('leave.reports')),
                'available' => (bool) (
                    $user?->can('leave.view')
                    || $user?->can('leave.request')
                    || $user?->can('leave.approve')
                    || $user?->can('leave.credits')
                    || $user?->can('leave.reports')
                ),
                'visible' => (bool) (
                    $user?->can('leave.view')
                    || $user?->can('leave.request')
                    || $user?->can('leave.approve')
                    || $user?->can('leave.credits')
                    || $user?->can('leave.reports')
                ),
                'active' => request()->routeIs('leave.*'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Requests',
                        'items' => [
                            ($user?->can('leave.view') || $user?->can('leave.request') || $user?->can('leave.approve'))
                                ? ['label' => 'Leave Requests', 'route' => 'leave.requests', 'icon' => 'calendar-off', 'active' => request()->routeIs('leave.requests*')]
                                : null,
                            ($user?->can('leave.approve') || $user?->can('leave.view'))
                                ? ['label' => 'Leave Approvals', 'route' => 'leave.approvals', 'icon' => 'user-check', 'active' => request()->routeIs('leave.approvals')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Credits',
                        'items' => [
                            ($user?->can('leave.credits') || $user?->can('leave.view'))
                                ? ['label' => 'Leave Credits', 'route' => 'leave.credits', 'icon' => 'coins', 'active' => request()->routeIs('leave.credits')]
                                : null,
                            ($user?->can('leave.credits') || $user?->can('leave.view'))
                                ? ['label' => 'Leave Card', 'route' => 'leave.card', 'icon' => 'book-text', 'active' => request()->routeIs('leave.card*')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Reports',
                        'items' => [
                            ($user?->can('leave.reports') || $user?->can('leave.view'))
                                ? ['label' => 'Leave Reports', 'route' => 'leave.reports', 'icon' => 'clipboard-list', 'active' => request()->routeIs('leave.reports')]
                                : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'scheduling',
                'label' => $isCnoSchedule ? 'Schedule (CNO)' : 'Schedule',
                'description' => 'Schedules, staffing, rotations, coverage, and duty operations.',
                'launcher_group' => 'operations',
                'accent' => 'cyan',
                'icon' => 'calendar-range',
                'href' => route('schedule.dashboard'),
                'available' => (bool) $user?->can('schedule.view'),
                'visible' => (bool) $user?->can('schedule.view'),
                'active' => request()->routeIs(
                    'schedule.dashboard',
                    'schedule.show',
                    'schedule.floaters',
                    'schedule.on-call',
                    'schedule.census',
                    'schedule.swaps',
                    'schedule.schedulev2-sync',
                    'schedule.print',
                    'schedule.pdf'
                ),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Operations',
                        'items' => [
                            $user?->can('schedule.view')
                                ? ['label' => 'Schedules', 'route' => 'schedule.dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('schedule.dashboard', 'schedule.show')]
                                : null,
                            ($user?->can('schedule.manage') || $user?->can('schedule.view'))
                                ? ['label' => 'Import from NDOS', 'route' => 'schedule.schedulev2-sync', 'icon' => 'upload', 'active' => request()->routeIs('schedule.schedulev2-sync')]
                                : null,
                            ($user?->can('schedule.view') && $scheduleProfile->uses_swaps)
                                ? ['label' => 'Shift Swaps', 'route' => 'schedule.swaps', 'icon' => 'arrow-left-right', 'active' => request()->routeIs('schedule.swaps')]
                                : null,
                            ($user?->can('schedule.view') && $scheduleProfile->uses_census)
                                ? ['label' => 'Duty Census', 'route' => 'schedule.census', 'icon' => 'chart-no-axes-column', 'active' => request()->routeIs('schedule.census')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Setup',
                        'items' => [
                            $user?->can('schedule.view') ? ['label' => 'Shift Codes', 'route' => 'schedule.shift-codes', 'icon' => 'clock-3', 'active' => request()->routeIs('schedule.shift-codes')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Employee Settings', 'route' => 'schedule.employees', 'icon' => 'user-cog', 'active' => request()->routeIs('schedule.employees')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Rotation Groups', 'route' => 'schedule.rotation-groups', 'icon' => 'refresh-cw', 'active' => request()->routeIs('schedule.rotation-groups')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Staffing', 'route' => 'schedule.staffing-requirements', 'icon' => 'clipboard-list', 'active' => request()->routeIs('schedule.staffing-requirements')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Templates', 'route' => 'schedule.templates', 'icon' => 'table-properties', 'active' => request()->routeIs('schedule.templates')] : null,
                            ($user?->can('schedule.view') && $scheduleProfile->uses_units)
                                ? ['label' => $unitsNavLabel, 'route' => 'schedule.units', 'icon' => 'building', 'active' => request()->routeIs('schedule.units')]
                                : null,
                            $user?->can('schedule.view')
                                ? ['label' => 'Dept Profile', 'route' => 'schedule.department-profiles', 'icon' => 'settings', 'active' => request()->routeIs('schedule.department-profiles')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Coverage',
                        'items' => [
                            ($user?->can('schedule.view') && $scheduleProfile->uses_floaters)
                                ? ['label' => 'Floaters', 'route' => 'schedule.floaters', 'icon' => 'refresh-cw', 'active' => request()->routeIs('schedule.floaters')]
                                : null,
                            ($user?->can('schedule.view') && $scheduleProfile->uses_on_call)
                                ? ['label' => 'On Call', 'route' => 'schedule.on-call', 'icon' => 'phone', 'active' => request()->routeIs('schedule.on-call')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Output',
                        'items' => [
                            $user?->can('schedule.view') ? ['label' => 'Print Settings', 'route' => 'schedule.print-settings', 'icon' => 'printer', 'active' => request()->routeIs('schedule.print-settings')] : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'timekeeping',
                'label' => 'Timekeeping',
                'description' => 'Attendance, DTR encoding, corrections, and device references.',
                'launcher_group' => 'operations',
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
                    'payroll.mra'
                ) || (request()->routeIs('coming-soon') && request()->route('module') === 'timekeeping'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Attendance',
                        'items' => [
                            ['label' => 'Daily Attendance', 'route' => 'payroll.daily-attendance', 'icon' => 'calendar-check', 'active' => request()->routeIs('payroll.daily-attendance')],
                            ['label' => 'Attendance Report', 'route' => 'payroll.attendance-report', 'icon' => 'clipboard-list', 'active' => request()->routeIs('payroll.attendance-report')],
                            ['label' => 'DTR Encoding', 'route' => 'payroll.dtr-encoding', 'icon' => 'file-clock', 'active' => request()->routeIs('payroll.dtr', 'payroll.dtr-encoding')],
                        ],
                    ],
                    [
                        'label' => 'Corrections',
                        'items' => [
                            ['label' => 'DTR Corrections', 'route' => 'payroll.dtr-correction-requests', 'icon' => 'file-pen-line', 'active' => request()->routeIs('payroll.dtr-correction-requests')],
                            ['label' => 'DTR Approvers', 'route' => 'payroll.dtr-correction-approvers', 'icon' => 'user-check', 'active' => request()->routeIs('payroll.dtr-correction-approvers')],
                        ],
                    ],
                    [
                        'label' => 'Devices & refs',
                        'items' => [
                            ['label' => 'Fingerprint Status', 'route' => 'payroll.fingerprint-registration', 'icon' => 'fingerprint', 'active' => request()->routeIs('payroll.fingerprint-registration')],
                            ['label' => 'MRA', 'route' => 'payroll.mra', 'icon' => 'chart-no-axes-column', 'active' => request()->routeIs('payroll.mra')],
                            ['label' => 'Holidays', 'route' => 'payroll.holidays', 'icon' => 'calendar-check', 'active' => request()->routeIs('payroll.holidays')],
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'description' => 'Payroll runs, history, loans, deductions, and compensation rules.',
                'launcher_group' => 'operations',
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
                    'payroll.history.import',
                    'payroll.history.imports',
                    'payroll.history.payslip.print',
                    'payroll.loan-imports',
                    'payroll.additional-premiums'
                ) || (request()->routeIs('coming-soon') && request()->route('module') === 'payroll'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Runs',
                        'items' => [
                            ['label' => 'Payroll Generation', 'route' => 'payroll.generation.configuration', 'icon' => 'banknote', 'active' => request()->routeIs('payroll.generation', 'payroll.generation.configuration', 'payroll.generation.hazard', 'payroll.generation.medicare')],
                            ['label' => 'Payroll History', 'route' => 'payroll.history', 'icon' => 'history', 'active' => request()->routeIs('payroll.history', 'payroll.history.payslip.print')],
                            ($user?->can('payroll.generation.hr') || $user?->can('payroll.generation.accounting'))
                                ? ['label' => 'Past Payroll Imports', 'route' => 'payroll.history.imports', 'icon' => 'history', 'active' => request()->routeIs('payroll.history.imports')]
                                : null,
                            ($user?->can('payroll.generation.hr') || $user?->can('payroll.generation.accounting'))
                                ? ['label' => 'Import Past Payroll', 'route' => 'payroll.history.import', 'icon' => 'upload', 'active' => request()->routeIs('payroll.history.import')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Loans',
                        'items' => [
                            ['label' => 'Loan Due Imports', 'route' => 'payroll.loan-imports', 'icon' => 'upload', 'active' => request()->routeIs('payroll.loan-imports')],
                            ['label' => 'Loan References', 'route' => 'payroll.loan-references', 'icon' => 'files', 'active' => request()->routeIs('payroll.loan-references')],
                        ],
                    ],
                    [
                        'label' => 'Configuration',
                        'items' => [
                            ['label' => 'Additional Premiums', 'route' => 'payroll.additional-premiums', 'icon' => 'coins', 'active' => request()->routeIs('payroll.additional-premiums')],
                            ['label' => 'Deduction Programs', 'route' => 'payroll.deduction-programs', 'icon' => 'list-checks', 'active' => request()->routeIs('payroll.deduction-programs')],
                            ['label' => 'Mandatory Deductions', 'route' => 'payroll.statutory-contributions', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.statutory-contributions')],
                            ['label' => 'Compensation Rules', 'route' => 'payroll.compensations', 'icon' => 'coins', 'active' => request()->routeIs('payroll.compensations')],
                            ['label' => 'Adjustment Types', 'route' => 'payroll.adjustment-types', 'icon' => 'sliders', 'active' => request()->routeIs('payroll.adjustment-types')],
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'training',
                'label' => 'Training',
                'description' => 'Training requests, approvals, development records, and calendar.',
                'launcher_group' => 'operations',
                'accent' => 'rose',
                'icon' => 'graduation-cap',
                'href' => ($user?->can('training.view') || $user?->can('training.manage') || $user?->can('training.approve'))
                    ? route('training.requests')
                    : route('training.approvals'),
                'available' => (bool) (
                    $user?->can('training.view')
                    || $user?->can('training.manage')
                    || $user?->can('training.approve')
                ),
                'visible' => (bool) (
                    $user?->can('training.view')
                    || $user?->can('training.manage')
                    || $user?->can('training.approve')
                ),
                'active' => request()->routeIs('training.*'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'TARF / LDI',
                        'items' => [
                            ($user?->can('training.view') || $user?->can('training.manage') || $user?->can('training.approve'))
                                ? ['label' => 'Requests', 'route' => 'training.requests', 'icon' => 'graduation-cap', 'active' => request()->routeIs('training.requests', 'training.show', 'training.print')]
                                : null,
                            ($user?->can('training.approve') || $user?->can('training.view'))
                                ? ['label' => 'Approvals', 'route' => 'training.approvals', 'icon' => 'user-check', 'active' => request()->routeIs('training.approvals')]
                                : null,
                            ($user?->can('training.view') || $user?->can('training.manage') || $user?->can('training.approve'))
                                ? ['label' => 'Calendar', 'route' => 'training.calendar', 'icon' => 'calendar-range', 'active' => request()->routeIs('training.calendar')]
                                : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'performance',
                'label' => 'Performance',
                'description' => 'IPCR periods, evaluation workflows, and approvals.',
                'launcher_group' => 'operations',
                'accent' => 'orange',
                'icon' => 'award',
                'href' => route('performance.periods'),
                'available' => (bool) (
                    $user?->can('performance.view')
                    || $user?->can('performance.manage')
                    || $user?->can('performance.approve')
                ),
                'visible' => (bool) (
                    $user?->can('performance.view')
                    || $user?->can('performance.manage')
                    || $user?->can('performance.approve')
                ),
                'active' => request()->routeIs('performance.employee', 'performance.print'),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'IPCR',
                        'items' => [
                            ($user?->can('performance.view') || $user?->can('performance.manage') || $user?->can('performance.approve'))
                                ? ['label' => 'IPCR Periods', 'route' => 'performance.periods', 'icon' => 'award', 'active' => request()->routeIs('performance.*')]
                                : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'setup',
                'label' => 'Setup',
                'description' => 'Organization, scheduling, timekeeping, payroll, and access configuration.',
                'launcher_group' => 'administration',
                'accent' => 'cyan',
                'icon' => 'settings',
                'href' => route('setup.index'),
                'available' => (bool) (
                    $user?->can('employees.manage')
                    || $user?->can('schedule.view')
                    || $user?->can('timekeeping.view')
                    || $user?->can('payroll.view')
                    || $user?->can('performance.view')
                    || $user?->can('admin.users.view')
                    || $user?->can('admin.roles.view')
                ),
                'visible' => (bool) (
                    $user?->can('employees.manage')
                    || $user?->can('schedule.view')
                    || $user?->can('timekeeping.view')
                    || $user?->can('payroll.view')
                    || $user?->can('performance.view')
                    || $user?->can('admin.users.view')
                    || $user?->can('admin.roles.view')
                ),
                'active' => request()->routeIs(
                    'setup.*',
                    'employees.masterlist-import',
                    'schedule.shift-codes',
                    'schedule.employees',
                    'schedule.rotation-groups',
                    'schedule.staffing-requirements',
                    'schedule.templates',
                    'schedule.print-settings',
                    'schedule.department-profiles',
                    'schedule.units',
                    'payroll.dtr-correction-approvers',
                    'payroll.fingerprint-registration',
                    'payroll.holidays',
                    'payroll.loan-references',
                    'payroll.deduction-programs',
                    'payroll.statutory-contributions',
                    'payroll.compensations',
                    'payroll.adjustment-types',
                    'performance.periods',
                    'admin.user-accounts',
                    'admin.roles-permissions'
                ),
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Organization & HRIS',
                        'items' => [
                            ($user?->can('employees.manage') || $user?->can('payroll.configure')) ? ['label' => 'Organization', 'route' => 'setup.organization', 'icon' => 'building', 'active' => request()->routeIs('setup.organization')] : null,
                            ($user?->can('employees.manage') || $user?->can('payroll.configure')) ? ['label' => 'Positions', 'route' => 'setup.positions', 'icon' => 'id-card', 'active' => request()->routeIs('setup.positions')] : null,
                            ($user?->can('employees.manage') || $user?->can('payroll.configure')) ? ['label' => 'Salary Schedules', 'route' => 'setup.salary-schedules', 'icon' => 'coins', 'active' => request()->routeIs('setup.salary-schedules')] : null,
                            ($user?->can('employees.manage') || $user?->can('payroll.configure')) ? ['label' => 'Plantilla Registry', 'route' => 'setup.plantilla', 'icon' => 'clipboard-list', 'active' => request()->routeIs('setup.plantilla')] : null,
                            $user?->can('employees.manage')
                                ? ['label' => 'Import Masterlist', 'route' => 'employees.masterlist-import', 'icon' => 'upload', 'active' => request()->routeIs('employees.masterlist-import')]
                                : null,
                        ],
                    ],
                    [
                        'label' => 'Scheduling',
                        'items' => [
                            $user?->can('schedule.view') ? ['label' => 'Shift Codes', 'route' => 'schedule.shift-codes', 'icon' => 'clock-3', 'active' => request()->routeIs('schedule.shift-codes')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Employee Settings', 'route' => 'schedule.employees', 'icon' => 'user-cog', 'active' => request()->routeIs('schedule.employees')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Rotation Groups', 'route' => 'schedule.rotation-groups', 'icon' => 'refresh-cw', 'active' => request()->routeIs('schedule.rotation-groups')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Staffing', 'route' => 'schedule.staffing-requirements', 'icon' => 'clipboard-list', 'active' => request()->routeIs('schedule.staffing-requirements')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Templates', 'route' => 'schedule.templates', 'icon' => 'table-properties', 'active' => request()->routeIs('schedule.templates')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Department Profiles', 'route' => 'schedule.department-profiles', 'icon' => 'building', 'active' => request()->routeIs('schedule.department-profiles')] : null,
                            $user?->can('schedule.view') ? ['label' => 'Print Settings', 'route' => 'schedule.print-settings', 'icon' => 'printer', 'active' => request()->routeIs('schedule.print-settings')] : null,
                        ],
                    ],
                    [
                        'label' => 'Timekeeping',
                        'items' => [
                            $user?->can('timekeeping.view') ? ['label' => 'DTR Approvers', 'route' => 'payroll.dtr-correction-approvers', 'icon' => 'user-check', 'active' => request()->routeIs('payroll.dtr-correction-approvers')] : null,
                            $user?->can('timekeeping.view') ? ['label' => 'Holidays', 'route' => 'payroll.holidays', 'icon' => 'calendar-check', 'active' => request()->routeIs('payroll.holidays')] : null,
                            $user?->can('timekeeping.view') ? ['label' => 'Fingerprint References', 'route' => 'payroll.fingerprint-registration', 'icon' => 'fingerprint', 'active' => request()->routeIs('payroll.fingerprint-registration')] : null,
                        ],
                    ],
                    [
                        'label' => 'Payroll',
                        'items' => [
                            $user?->can('payroll.view') ? ['label' => 'Compensation Rules', 'route' => 'payroll.compensations', 'icon' => 'coins', 'active' => request()->routeIs('payroll.compensations')] : null,
                            $user?->can('payroll.view') ? ['label' => 'Mandatory Deductions', 'route' => 'payroll.statutory-contributions', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.statutory-contributions')] : null,
                            $user?->can('payroll.view') ? ['label' => 'Deduction Programs', 'route' => 'payroll.deduction-programs', 'icon' => 'list-checks', 'active' => request()->routeIs('payroll.deduction-programs')] : null,
                            $user?->can('payroll.view') ? ['label' => 'Adjustment Types', 'route' => 'payroll.adjustment-types', 'icon' => 'sliders', 'active' => request()->routeIs('payroll.adjustment-types')] : null,
                            $user?->can('payroll.view') ? ['label' => 'Loan References', 'route' => 'payroll.loan-references', 'icon' => 'files', 'active' => request()->routeIs('payroll.loan-references')] : null,
                        ],
                    ],
                    [
                        'label' => 'Performance',
                        'items' => [
                            $user?->can('performance.view') ? ['label' => 'IPCR Periods', 'route' => 'performance.periods', 'icon' => 'award', 'active' => request()->routeIs('performance.periods')] : null,
                        ],
                    ],
                    [
                        'label' => 'Access & Security',
                        'items' => [
                            $user?->can('admin.users.view') ? ['label' => 'User Accounts', 'route' => 'admin.user-accounts', 'icon' => 'users', 'active' => request()->routeIs('admin.user-accounts')] : null,
                            $user?->can('admin.roles.view') ? ['label' => 'Roles & Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'shield-check', 'active' => request()->routeIs('admin.roles-permissions')] : null,
                        ],
                    ],
                ]),
            ],
            [
                'key' => 'administration',
                'label' => 'Settings',
                'description' => 'User access, permissions, references, and system manuals.',
                'launcher_group' => 'administration',
                'accent' => 'slate',
                'icon' => 'gears',
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
                'menu_sections' => self::menuSections([
                    [
                        'label' => 'Access',
                        'items' => [
                            $user?->can('admin.users.view') ? ['label' => 'User Accounts', 'route' => 'admin.user-accounts', 'icon' => 'users', 'active' => request()->routeIs('admin.user-accounts')] : null,
                            $user?->can('admin.roles.view') ? ['label' => 'Roles and Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'shield-check', 'active' => request()->routeIs('admin.roles-permissions')] : null,
                        ],
                    ],
                    [
                        'label' => 'References & manuals',
                        'items' => [
                            $user?->can('references.view') ? ['label' => 'Employee References', 'route' => 'schedule.employee-references', 'icon' => 'database', 'active' => request()->routeIs('schedule.employee-references')] : null,
                            $user?->can('references.view') ? ['label' => 'Schedule Manual', 'route' => 'schedule.user-manual', 'icon' => 'book-text', 'active' => request()->routeIs('schedule.user-manual')] : null,
                            $user?->can('references.view') ? ['label' => 'Payroll Manual', 'route' => 'payroll.user-manual', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.user-manual')] : null,
                            $user?->can('references.view') ? ['label' => 'Roles Manual', 'route' => 'references.roles-permissions-manual', 'icon' => 'shield-check', 'active' => request()->routeIs('references.roles-permissions-manual')] : null,
                        ],
                    ],
                ]),
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
     * @param  list<array<string, mixed>>|null  $apps
     * @return list<array{key: string, label: string, description: string, modules: list<array<string, mixed>>}>
     */
    public static function launcherGroups(?array $apps = null): array
    {
        $apps ??= self::visibleApps();

        $definitions = [
            'workspace' => ['label' => 'My Workspace', 'description' => 'Your personal records and employee services.'],
            'operations' => ['label' => 'Workforce Operations', 'description' => 'People, time, development, and payroll operations.'],
            'administration' => ['label' => 'Administration', 'description' => 'Configuration, access, references, and system controls.'],
        ];

        $groups = [];

        foreach ($definitions as $key => $definition) {
            $modules = array_values(array_filter(
                $apps,
                fn (array $app): bool => ($app['launcher_group'] ?? 'operations') === $key,
            ));

            if ($modules === []) {
                continue;
            }

            $groups[] = ['key' => $key, ...$definition, 'modules' => $modules];
        }

        return $groups;
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
            'fingerprint' => 'M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4 M14 13.12c0 2.38 0 6.38-1 8.88 M17.29 21.02c.12-.6.43-2.3.5-3.02 M2 12a10 10 0 0 1 18-6 M2 16h.01 M21.8 16c.2-2 .131-5.354 0-6 M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2 M8.65 22c.21-.66.45-1.32.57-2 M9 6.8a6 6 0 0 1 9 5.2v2',
            'graduation-cap' => 'M22 10v6 M2 10l10-5 10 5-10 5z M6 12v5c3 3 9 3 12 0v-5',
            'gears' => 'M9 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M9 5V3 M9 21v-2 M5.5 6.5 4 5 M14 19l-1.5-1.5 M5 12H3 M15 12h-2 M5.5 17.5 4 19 M14 5l-1.5 1.5 M18 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z M18 2V1 M18 12v-1 M14.5 3.5l-.8-.8 M22.3 11.3l-.8-.8 M14 6.5h-1 M23 6.5h-1 M14.5 9.5l-.8.8 M22.3 1.7l-.8.8',
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
