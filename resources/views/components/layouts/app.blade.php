<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payroll Scheduler' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    @php
        $account = auth()->user()?->loadMissing('employee');
        $employeeName = $account?->employee?->full_name ?: $account?->emp_id;
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-b-0 lg:border-r">
            <div class="flex min-h-full flex-col">
                <div class="border-b border-slate-200 px-5 py-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MMMHMC</p>
                    <h1 class="mt-1 text-xl font-semibold">Payroll Scheduler</h1>
                </div>

                <nav
                    class="space-y-2 px-3 py-4 text-sm"
                    x-data="{
                        open: {
                            scheduling: {{ request()->routeIs('schedule.dashboard') ? 'true' : 'false' }},
                            setup: {{ request()->routeIs('schedule.shift-codes', 'schedule.employees', 'schedule.rotation-groups', 'schedule.staffing-requirements', 'schedule.templates', 'schedule.print-settings') ? 'true' : 'false' }},
                            payroll: {{ request()->routeIs('payroll.*') ? 'true' : 'false' }},
                            references: {{ request()->routeIs('schedule.employee-references', 'schedule.user-manual') ? 'true' : 'false' }},
                        }
                    }"
                >
                    <div class="rounded-lg border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50"
                            x-on:click="open.scheduling = ! open.scheduling"
                            :aria-expanded="open.scheduling.toString()"
                        >
                            <span>Scheduling</span>
                            <span class="text-slate-400" x-text="open.scheduling ? '−' : '+'"></span>
                        </button>
                        <div class="space-y-1 px-2 pb-2" x-show="open.scheduling">
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.dashboard') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.dashboard') }}"
                            >
                                Schedule Dashboard
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50"
                            x-on:click="open.setup = ! open.setup"
                            :aria-expanded="open.setup.toString()"
                        >
                            <span>Schedule Setup</span>
                            <span class="text-slate-400" x-text="open.setup ? '−' : '+'"></span>
                        </button>
                        <div class="space-y-1 px-2 pb-2" x-show="open.setup">
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.shift-codes') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.shift-codes') }}"
                            >
                                Shift Codes
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.employees') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.employees') }}"
                            >
                                Employee Schedule Settings
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.rotation-groups') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.rotation-groups') }}"
                            >
                                Rotation Groups
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.staffing-requirements') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.staffing-requirements') }}"
                            >
                                Staffing Requirements
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.templates') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.templates') }}"
                            >
                                Schedule Templates
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.print-settings') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.print-settings') }}"
                            >
                                Print and Export Settings
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50"
                            x-on:click="open.payroll = ! open.payroll"
                            :aria-expanded="open.payroll.toString()"
                        >
                            <span>Payroll Operations</span>
                            <span class="text-slate-400" x-text="open.payroll ? '−' : '+'"></span>
                        </button>
                        <div class="space-y-1 px-2 pb-2" x-show="open.payroll">
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('payroll.dtr') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('payroll.dtr') }}"
                            >
                                DTR Review
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('payroll.dtr-encoding') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('payroll.dtr-encoding') }}"
                            >
                                DTR Encoding
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('payroll.mra') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('payroll.mra') }}"
                            >
                                MRA
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('payroll.generation') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('payroll.generation') }}"
                            >
                                Payroll Generation
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('payroll.compensations') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('payroll.compensations') }}"
                            >
                                Compensation Rules
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50"
                            x-on:click="open.references = ! open.references"
                            :aria-expanded="open.references.toString()"
                        >
                            <span>References and Help</span>
                            <span class="text-slate-400" x-text="open.references ? '−' : '+'"></span>
                        </button>
                        <div class="space-y-1 px-2 pb-2" x-show="open.references">
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.employee-references') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.employee-references') }}"
                            >
                                Employee References
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 {{ request()->routeIs('schedule.user-manual') ? 'bg-blue-50 font-medium text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                href="{{ route('schedule.user-manual') }}"
                            >
                                User Manual
                            </a>
                            <a
                                class="flex items-center rounded-md px-3 py-2.5 text-slate-700 hover:bg-slate-100"
                                href="/api/employees"
                            >
                                Employees API
                            </a>
                        </div>
                    </div>
                </nav>

                <div class="mt-auto border-t border-slate-200 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Signed In</p>
                    <p class="mt-1 truncate text-sm font-medium text-slate-900">{{ $employeeName }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $account?->emp_id }}</p>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="min-w-0">
            <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
                <div class="flex w-full items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Workspace</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ $title ?? 'Payroll Scheduler' }}</h2>
                    </div>
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-slate-900">{{ $employeeName }}</p>
                        <p class="text-xs text-slate-500">{{ $account?->emp_id }}</p>
                    </div>
                </div>
            </header>

            <main class="w-full px-4 py-6 sm:px-6">
                {{ $slot }}
            </main>
        </section>
    </div>

    @livewireScripts
</body>
</html>
