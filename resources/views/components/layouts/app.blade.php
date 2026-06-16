<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payroll Scheduler' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="erp-body bg-[#f5f5f9] text-[#2f3349] antialiased">
    @php
        $account = auth()->user()?->loadMissing('employee');
        $employeeName = $account?->employee?->full_name ?: $account?->emp_id;
        $initial = strtoupper(substr((string) $employeeName, 0, 1));
        $navGroups = [
            [
                'key' => 'self_service',
                'label' => 'Self Service',
                'icon' => 'clock-3',
                'visible' => true,
                'open' => request()->routeIs('time-punch.*'),
                'items' => [
                    ['label' => 'Time Punch', 'route' => 'time-punch.index', 'icon' => 'clock-3', 'active' => request()->routeIs('time-punch.*')],
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'Administration',
                'icon' => 'shield-check',
                'visible' => auth()->user()?->can('admin.users.view') || auth()->user()?->can('admin.roles.view'),
                'open' => request()->routeIs('admin.user-accounts', 'admin.roles-permissions'),
                'items' => [
                    ['label' => 'User Accounts', 'route' => 'admin.user-accounts', 'icon' => 'users', 'active' => request()->routeIs('admin.user-accounts'), 'visible' => auth()->user()?->can('admin.users.view')],
                    ['label' => 'Roles and Permissions', 'route' => 'admin.roles-permissions', 'icon' => 'shield-check', 'active' => request()->routeIs('admin.roles-permissions'), 'visible' => auth()->user()?->can('admin.roles.view')],
                ],
            ],
            [
                'key' => 'scheduling',
                'label' => 'Scheduling',
                'icon' => 'calendar-range',
                'visible' => auth()->user()?->can('schedule.view'),
                'open' => request()->routeIs('schedule.dashboard', 'schedule.shift-codes', 'schedule.employees', 'schedule.rotation-groups', 'schedule.staffing-requirements', 'schedule.templates', 'schedule.print-settings'),
                'items' => [
                    ['label' => 'Schedule Dashboard', 'route' => 'schedule.dashboard', 'icon' => 'layout-dashboard', 'active' => request()->routeIs('schedule.dashboard')],
                    [
                        'key' => 'scheduling_setup',
                        'label' => 'Schedule Setup',
                        'icon' => 'sliders',
                        'open' => request()->routeIs('schedule.shift-codes', 'schedule.employees', 'schedule.rotation-groups', 'schedule.staffing-requirements', 'schedule.templates', 'schedule.print-settings'),
                        'children' => [
                            ['label' => 'Shift Codes', 'route' => 'schedule.shift-codes', 'icon' => 'clock-3', 'active' => request()->routeIs('schedule.shift-codes')],
                            ['label' => 'Employee Schedule Settings', 'route' => 'schedule.employees', 'icon' => 'user-cog', 'active' => request()->routeIs('schedule.employees')],
                            ['label' => 'Rotation Groups', 'route' => 'schedule.rotation-groups', 'icon' => 'refresh-cw', 'active' => request()->routeIs('schedule.rotation-groups')],
                            ['label' => 'Staffing Requirements', 'route' => 'schedule.staffing-requirements', 'icon' => 'clipboard-list', 'active' => request()->routeIs('schedule.staffing-requirements')],
                            ['label' => 'Schedule Templates', 'route' => 'schedule.templates', 'icon' => 'table-properties', 'active' => request()->routeIs('schedule.templates')],
                            ['label' => 'Print and Export Settings', 'route' => 'schedule.print-settings', 'icon' => 'printer', 'active' => request()->routeIs('schedule.print-settings')],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll Operations',
                'icon' => 'wallet',
                'visible' => auth()->user()?->can('payroll.view'),
                'open' => request()->routeIs(
                    'payroll.generation',
                    'payroll.generation.configuration',
                    'payroll.generation.hazard',
                    'payroll.generation.medicare',
                    'payroll.history',
                    'payroll.loan-imports',
                    'payroll.loan-references',
                    'payroll.deduction-programs',
                    'payroll.statutory-contributions',
                    'payroll.compensations',
                    'payroll.adjustment-types'
                ),
                'items' => [
                    ['label' => 'Payroll Generation', 'route' => 'payroll.generation.configuration', 'icon' => 'banknote', 'active' => request()->routeIs('payroll.generation', 'payroll.generation.configuration', 'payroll.generation.hazard', 'payroll.generation.medicare')],
                    ['label' => 'Payroll History', 'route' => 'payroll.history', 'icon' => 'history', 'active' => request()->routeIs('payroll.history')],
                    ['label' => 'Loan Due Imports', 'route' => 'payroll.loan-imports', 'icon' => 'upload', 'active' => request()->routeIs('payroll.loan-imports')],
                    ['label' => 'Loan References', 'route' => 'payroll.loan-references', 'icon' => 'files', 'active' => request()->routeIs('payroll.loan-references')],
                    ['label' => 'Deduction Programs', 'route' => 'payroll.deduction-programs', 'icon' => 'list-checks', 'active' => request()->routeIs('payroll.deduction-programs')],
                    ['label' => 'Mandatory Deductions', 'route' => 'payroll.statutory-contributions', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.statutory-contributions')],
                    ['label' => 'Compensation Rules', 'route' => 'payroll.compensations', 'icon' => 'coins', 'active' => request()->routeIs('payroll.compensations')],
                    ['label' => 'Adjustment Types', 'route' => 'payroll.adjustment-types', 'icon' => 'sliders', 'active' => request()->routeIs('payroll.adjustment-types')],
                ],
            ],
            [
                'key' => 'timekeeping',
                'label' => 'Timekeeping',
                'icon' => 'file-clock',
                'visible' => auth()->user()?->can('timekeeping.view'),
                'open' => request()->routeIs(
                    'payroll.daily-attendance',
                    'payroll.attendance-report',
                    'payroll.dtr',
                    'payroll.dtr-encoding',
                    'payroll.dtr-correction-requests',
                    'payroll.dtr-correction-approvers',
                    'payroll.mra',
                    'payroll.holidays'
                ),
                'items' => [
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
                'key' => 'references',
                'label' => 'References and Help',
                'icon' => 'book-open',
                'visible' => auth()->user()?->can('references.view'),
                'open' => request()->routeIs('schedule.employee-references', 'schedule.user-manual', 'payroll.user-manual', 'references.roles-permissions-manual'),
                'items' => [
                    ['label' => 'Employee References', 'route' => 'schedule.employee-references', 'icon' => 'database', 'active' => request()->routeIs('schedule.employee-references')],
                    ['label' => 'User Manual', 'route' => 'schedule.user-manual', 'icon' => 'book-text', 'active' => request()->routeIs('schedule.user-manual')],
                    ['label' => 'Payroll Operations Manual', 'route' => 'payroll.user-manual', 'icon' => 'wallet', 'active' => request()->routeIs('payroll.user-manual')],
                    ['label' => 'Roles and Permissions Manual', 'route' => 'references.roles-permissions-manual', 'icon' => 'shield-check', 'active' => request()->routeIs('references.roles-permissions-manual')],
                    ['label' => 'Employees API', 'href' => '/api/employees', 'icon' => 'braces', 'active' => false],
                ],
            ],
        ];
        $icons = [
            'banknote' => 'M6 18h12 M6 6h12 M6 6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2 M18 6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2 M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
            'book-open' => 'M12 7v14 M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 0 4 18.5z M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 1 2.5-2.5z',
            'book-text' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20 M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z M8 7h8 M8 11h8 M8 15h5',
            'braces' => 'M8 3H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1 M16 3h1a2 2 0 0 1 2 2v4a2 2 0 0 0 2 2 2 2 0 0 0-2 2v4a2 2 0 0 1-2 2h-1',
            'calendar-check' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M9 16l2 2 4-5',
            'calendar-range' => 'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M8 14h3 M13 14h3 M8 18h8',
            'chart-no-axes-column' => 'M5 21V10 M12 21V3 M19 21v-7',
            'clipboard-list' => 'M9 5h6 M9 12h6 M9 16h6 M7 5h.01 M7 12h.01 M7 16h.01 M9 3h6l1 2h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2z',
            'clock-3' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 6v6h4',
            'coins' => 'M9 8c0 1.7-2.2 3-5 3s-5-1.3-5-3 2.2-3 5-3 5 1.3 5 3z M4 11v4c0 1.7 2.2 3 5 3s5-1.3 5-3v-4 M14 8c2.8.2 5 1.4 5 3v4c0 1.7-2.2 3-5 3-.7 0-1.4-.1-2-.3',
            'database' => 'M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3 M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6 M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6',
            'file-clock' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M12 13v3l2 1',
            'file-pen-line' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M16 13l3 3 M10 20l8.5-8.5a2.1 2.1 0 0 1 3 3L13 23l-4 1z',
            'files' => 'M15 2H6a2 2 0 0 0-2 2v13 M8 6h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
            'layout-dashboard' => 'M3 3h8v8H3z M13 3h8v5h-8z M13 10h8v11h-8z M3 13h8v8H3z',
            'list-checks' => 'M10 6h10 M10 12h10 M10 18h10 M4 6l1.5 1.5L8 5 M4 12l1.5 1.5L8 11 M4 18l1.5 1.5L8 17',
            'printer' => 'M6 9V2h12v7 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v8H6z',
            'refresh-cw' => 'M21 12a9 9 0 0 1-15.5 6.3L3 16 M3 21v-5h5 M3 12A9 9 0 0 1 18.5 5.7L21 8 M21 3v5h-5',
            'sliders' => 'M4 21v-7 M4 10V3 M12 21v-9 M12 8V3 M20 21v-5 M20 12V3 M2 14h4 M10 8h4 M18 16h4',
            'shield-check' => 'M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3z M9 12l2 2 4-4',
            'table-properties' => 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 9h18 M9 9v12 M14 13h4 M14 17h4',
            'upload' => 'M12 3v12 M7 8l5-5 5 5 M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
            'user-check' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M16 11l2 2 4-4',
            'user-cog' => 'M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M2 21a8 8 0 0 1 12.2-6.8 M19 15v2 M19 21v.01 M22 18h-2 M18 18h-2 M21.1 15.9l-1.4 1.4 M18.3 20.7l-1.4 1.4 M21.1 22.1l-1.4-1.4 M18.3 17.3l-1.4-1.4',
            'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.9 M16 3.1a4 4 0 0 1 0 7.8',
            'wallet' => 'M20 7V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V7 M16 14h.01',
            'history' => 'M3 12a9 9 0 1 0 9-9 M12 7v5l3 3',
        ];
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <aside class="erp-sidebar border-b border-[#e4e6ef] bg-white lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-b-0 lg:border-r">
            <div class="flex min-h-full flex-col">
                <div class="border-b border-[#eceef6] px-4 py-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-[#696cff] text-sm font-bold text-white shadow-sm shadow-[#696cff]/25">PM</div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase text-[#8a8d93]">MMMHMC ERP</p>
                            <h1 class="truncate text-base font-semibold text-[#2f3349]">Payroll Scheduler</h1>
                        </div>
                    </div>
                </div>

                <nav
                    class="space-y-1 px-3 py-3 text-sm"
                    x-data="{ open: @js(collect($navGroups)
                        ->flatMap(fn ($group) => array_merge(
                            [$group['key'] => $group['open']],
                            collect($group['items'] ?? [])
                                ->filter(fn ($item) => isset($item['children']))
                                ->mapWithKeys(fn ($item) => [$item['key'] => $item['open']])
                                ->all()
                        ))
                        ->all()) }"
                >
                    @foreach ($navGroups as $group)
                        @continue(! ($group['visible'] ?? true))
                        <div class="erp-nav-group">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[11px] font-semibold uppercase text-[#8a8d93] hover:bg-[#f5f5f9]"
                                x-on:click="open.{{ $group['key'] }} = ! open.{{ $group['key'] }}"
                                :aria-expanded="open.{{ $group['key'] }}.toString()"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="erp-nav-section-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="{{ $icons[$group['icon']] }}"></path>
                                        </svg>
                                    </span>
                                    <span class="truncate">{{ $group['label'] }}</span>
                                </span>
                                <span class="text-sm text-[#a8abb4]" x-text="open.{{ $group['key'] }} ? '-' : '+'"></span>
                            </button>

                            <div class="mt-1 space-y-0.5 pb-1" x-show="open.{{ $group['key'] }}">
                                @foreach ($group['items'] as $item)
                                    @continue(! ($item['visible'] ?? true))
                                    @if (isset($item['children']))
                                        <button
                                            type="button"
                                            class="erp-nav-link erp-nav-link-depth-1 w-full {{ collect($item['children'])->contains(fn ($child) => $child['active'] ?? false) ? 'erp-nav-link-parent-active' : '' }}"
                                            x-on:click="open.{{ $item['key'] }} = ! open.{{ $item['key'] }}"
                                            :aria-expanded="open.{{ $item['key'] }}.toString()"
                                        >
                                            <span class="erp-nav-item-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="{{ $icons[$item['icon']] }}"></path>
                                                </svg>
                                            </span>
                                            <span class="truncate">{{ $item['label'] }}</span>
                                            <span class="ml-auto text-sm text-[#a8abb4]" x-text="open.{{ $item['key'] }} ? '-' : '+'"></span>
                                        </button>

                                        <div class="space-y-0.5" x-show="open.{{ $item['key'] }}">
                                            @foreach ($item['children'] as $child)
                                                @continue(! ($child['visible'] ?? true))
                                                <a
                                                    class="erp-nav-link erp-nav-link-depth-2 {{ $child['active'] ? 'erp-nav-link-active' : '' }}"
                                                    href="{{ isset($child['route']) ? route($child['route']) : $child['href'] }}"
                                                >
                                                    <span class="erp-nav-item-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="{{ $icons[$child['icon']] }}"></path>
                                                        </svg>
                                                    </span>
                                                    <span class="truncate">{{ $child['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <a
                                            class="erp-nav-link erp-nav-link-depth-1 {{ $item['active'] ? 'erp-nav-link-active' : '' }}"
                                            href="{{ isset($item['route']) ? route($item['route']) : $item['href'] }}"
                                        >
                                            <span class="erp-nav-item-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="{{ $icons[$item['icon']] }}"></path>
                                                </svg>
                                            </span>
                                            <span class="truncate">{{ $item['label'] }}</span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="mt-auto border-t border-[#eceef6] px-4 py-4">
                    <div class="rounded-md bg-[#f7f7fb] p-3">
                        <p class="text-[11px] font-semibold uppercase text-[#8a8d93]">Signed In</p>
                        <p class="mt-1 truncate text-sm font-semibold text-[#2f3349]">{{ $employeeName }}</p>
                        <p class="truncate text-xs text-[#697a8d]">{{ $account?->emp_id }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-md border border-[#d9dee8] bg-white px-3 py-2 text-sm font-medium text-[#566a7f] hover:bg-[#f5f5f9]">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="sticky top-0 z-30 border-b border-[#e4e6ef] bg-white/90 px-4 py-3 backdrop-blur sm:px-5">
                <div class="flex w-full items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase text-[#8a8d93]">Workspace</p>
                        <h2 class="mt-0.5 truncate text-base font-semibold text-[#2f3349]">{{ $title ?? 'Payroll Scheduler' }}</h2>
                    </div>
                    <div class="hidden items-center gap-3 text-right sm:flex">
                        <div>
                            <p class="text-sm font-semibold text-[#2f3349]">{{ $employeeName }}</p>
                            <p class="text-xs text-[#697a8d]">{{ $account?->emp_id }}</p>
                        </div>
                        <div class="grid h-8 w-8 place-items-center rounded-full bg-[#f1f2ff] text-xs font-bold text-[#696cff]">{{ $initial }}</div>
                    </div>
                </div>
            </header>

            <main class="erp-content w-full px-3 py-4 sm:px-5">
                {{ $slot }}
            </main>
        </section>
    </div>

    @livewireScripts
</body>
</html>
