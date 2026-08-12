<x-layouts.app title="Schedule Management User Manual">
    <section class="mx-auto max-w-7xl space-y-6">
        <div class="border-b border-slate-200 pb-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">User Manual</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Schedule Management</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Use this guide to set up schedule references, generate monthly schedules, validate conflicts,
                approve final rosters, lock schedules, and sync approved work hours into payroll operations.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="xl:sticky xl:top-6 xl:self-start">
                <nav class="rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Manual Navigation</p>
                    <div class="mt-3 space-y-1">
                        <a href="#flowchart" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">1. Flowchart</a>
                        <a href="#sample-setup" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">2. Sample Setup</a>
                        <a href="#use-cases" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">3. Use Cases</a>
                        <a href="#step-by-step" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">4. Step-by-Step Manual</a>
                        <a href="#cutover" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">5. Cutover</a>
                        <a href="#summary" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">6. Summary</a>
                    </div>
                </nav>
            </aside>

            <div class="space-y-6">
                <section id="flowchart" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">1. Flowchart</p>
                            <h3 class="mt-1 text-lg font-semibold">Schedule Management Process</h3>
                        </div>
                        <span class="rounded-md bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">Setup to Payroll</span>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                        @foreach ([
                            ['Setup References', 'Shift codes, employees, groups, staffing rules, and templates.'],
                            ['Create Month', 'Non-CNO: Generate New Schedule modal. CNO: Import from NDOS only (no draft generate).'],
                            ['Validate', 'Check conflicts, staffing gaps, duplicate assignments, and hour limits.'],
                            ['Review', 'Department reviewer checks draft coverage and corrections.'],
                            ['Approve and Lock', 'Finalize the schedule and prevent accidental changes.'],
                            ['Payroll Sync', 'Use locked schedules for DTR and payroll generation.'],
                        ] as [$title, $description])
                            <div class="relative rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-blue-700 text-sm font-semibold text-white">
                                    {{ $loop->iteration }}
                                </div>
                                <h4 class="mt-3 text-sm font-semibold text-slate-950">{{ $title }}</h4>
                                <p class="mt-2 text-xs leading-5 text-slate-600">{{ $description }}</p>
                                @unless ($loop->last)
                                    <div class="absolute -right-2 top-1/2 hidden h-4 w-4 rotate-45 border-r border-t border-slate-300 bg-slate-50 md:block"></div>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="sample-setup" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">2. Sample Setup</p>
                    <h3 class="mt-1 text-lg font-semibold">CNO / Nursing vs Department + Areas</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Schedule mode follows HRIS <code class="text-xs">tbl_department.division_id</code>.
                        Division <strong>{{ config('schedule.cno_division_id', 3) }}</strong> (Nursing Service / CNO) gets NDOS-parity defaults.
                        Other divisions stay on a simple department roster and may enable <strong>areas only</strong> for multi-area offices.
                        Optional flags never bypass draft → review → approve → <strong>lock → DTR sync</strong>.
                    </p>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <article class="rounded-lg border border-cyan-200 bg-cyan-50 p-4">
                            <h4 class="text-sm font-semibold text-cyan-950">CNO / Nursing (division_id = {{ config('schedule.cno_division_id', 3) }})</h4>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-cyan-900">
                                <li>Profile defaults: <strong>units, floaters, on-call, swaps, census</strong> all on (NDOS parity).</li>
                                <li>Units = wards / clinics / areas; schedulers can be scoped via handled units.</li>
                                <li>Schedules list is the entry point; open a month to edit the grid. CNO cannot generate drafts.</li>
                                <li>Import assignments: Schedules → <strong>Import from NDOS</strong> (<code class="text-xs">/schedule/schedulev2-sync</code>), or artisan <code class="text-xs">schedule:sync-schedulev2 --division={{ config('schedule.cno_division_id', 3) }} --dry-run</code>.</li>
                                <li>Full reference backfill (destructive): <code class="text-xs">php artisan schedule:backfill-schedulev2 --dry-run</code> then <code class="text-xs">--apply --force</code> (optional <code class="text-xs">--with-assignments</code>). Or Import page “Full backfill” with typed <code class="text-xs">BACKFILL</code>.</li>
                                <li>Provision profiles: <code class="text-xs">php artisan schedule:provision-department-profiles --apply</code>.</li>
                            </ul>
                        </article>
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">Other divisions — Department + areas</h4>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-600">
                                <li>Default profile: all optional flags <strong>off</strong> (simple office roster).</li>
                                <li>For multi-area under an office/department: enable <strong>uses_units</strong> only (UI labels say Areas).</li>
                                <li>Do not force floaters / on-call / census / swaps — turn those on only if the department needs them.</li>
                                <li>Set up shift codes, employee settings, templates, then generate monthly drafts.</li>
                                <li>Employees view published rosters under Self Service → My Schedule.</li>
                            </ul>
                        </article>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-3">Setup Area</th>
                                    <th class="px-3 py-3">Sample Values</th>
                                    <th class="px-3 py-3">Purpose</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="px-3 py-3 font-medium">Shift Codes</td>
                                    <td class="px-3 py-3">AM 07:00-15:00, PM 15:00-23:00, NOC 23:00-07:00, RD Rest Day</td>
                                    <td class="px-3 py-3 text-slate-600">Defines the actual work periods used by schedule assignments.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Employees</td>
                                    <td class="px-3 py-3">Employee ID, department, section, position, default work hours</td>
                                    <td class="px-3 py-3 text-slate-600">Identifies who can be scheduled and where they belong.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Units / Areas (optional)</td>
                                    <td class="px-3 py-3">WARD-A, ER, OPD-CLINIC, ADMIN-SEC / AREA-1</td>
                                    <td class="px-3 py-3 text-slate-600">Sub-department locations when the profile enables units (CNO wards or multi-area offices).</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Rotation Groups</td>
                                    <td class="px-3 py-3">Ward A Team 1, Ward A Team 2, ER Night Team</td>
                                    <td class="px-3 py-3 text-slate-600">Groups employees for rotating templates and coverage planning.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Staffing Requirements</td>
                                    <td class="px-3 py-3">Ward A requires 3 AM, 3 PM, 2 NOC per day</td>
                                    <td class="px-3 py-3 text-slate-600">Sets minimum staffing levels for validation.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Schedule Templates</td>
                                    <td class="px-3 py-3">5-day regular weekdays, 4-week rotating AM/PM/NOC pattern</td>
                                    <td class="px-3 py-3 text-slate-600">Speeds up monthly schedule draft generation.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="use-cases" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">3. Use Cases</p>
                    <h3 class="mt-1 text-lg font-semibold">Common Schedule Management Scenarios</h3>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ([
                            ['Monthly roster generation', 'The scheduler prepares the next month by selecting the year, month, department, and template, then generates a draft for review.'],
                            ['Employee reassignment', 'An employee transfers sections or joins a rotation group, then future schedules use the updated employee reference and group membership.'],
                            ['Staffing gap correction', 'Validation shows missing staff on a night shift, so the scheduler updates assignments before approval.'],
                            ['Approved payroll basis', 'Once the schedule is approved and locked, payroll staff use it as a reference for DTR checking and payroll generation.'],
                        ] as [$title, $description])
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-semibold text-slate-950">{{ $title }}</h4>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="step-by-step" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">4. Step-by-Step Manual</p>
                    <h3 class="mt-1 text-lg font-semibold">How to Use Schedule Management</h3>

                    <div class="mt-5 space-y-5">
                        @foreach ([
                            ['Configure department profile', 'Open Department Schedule Profile. CNO / Nursing departments (division_id from schedule.cno_division_id) default to full nursing flags. Other departments leave optional flags off for a simple roster, or enable Areas (uses_units) only for multi-area offices.'],
                            ['Configure shift codes', 'Open Shift Codes, create the required work periods, set start and end time, work hours, night shift flag, and active status.'],
                            ['Review employee references', 'Open Employee References and confirm department, section, position, schedule eligibility, and default settings are correct.'],
                            ['Create schedule units/areas (if enabled)', 'When uses_units is on, open Units (CNO) or Areas (other divisions) and add wards/sections/clinics/areas. Optionally assign handled units so each scheduler is limited to specific units.'],
                            ['Manage floater / on-call pools (if enabled)', 'When those flags are on (default for CNO), maintain permanent and monthly floater lists, and primary/second on-call pools. Mark temporary floater days on the dashboard grid.'],
                            ['Create rotation groups', 'Open Rotation Groups, create teams by department or unit, then assign employees to the correct group.'],
                            ['Set staffing requirements', 'Open Staffing Requirements and encode the minimum required headcount per department, shift, day, and effective period.'],
                            ['Build schedule templates', 'Open Schedule Templates and define reusable daily patterns such as regular weekdays, rotating shifts, rest days, or night duty patterns.'],
                            ['Generate a monthly draft (non-CNO)', 'Open Schedules → Generate New Schedule. Choose Automated (Beta) to allocate from weekly duties/templates, or Manual for blank OFF shifts you fill in. CNO must Import from NDOS instead.'],
                            ['Pattern-fill drafts', 'On the dashboard Pattern fill panel, pick a template/rotation pattern, select employees (checkboxes) or use Filtered/All scope, set a date range, click Preview, then Apply. Locked months cannot be edited. Row “Preview pattern…” loads one employee into the panel.'],
                            ['Validate the draft', 'Click Validate to check conflicts and gaps. Correct duplicate assignments, missing coverage, inactive employees, or invalid shift combinations. Use Duty Census when enabled to review day × shift headcount.'],
                            ['Review and approve', 'Click Review when the draft is ready, then Approve after the accountable reviewer confirms the roster.'],
                            ['Print, PDF, and email', 'Use Print / Export for the branded HTML sheet. Download PDF from the dashboard or Print Settings → PDF / email distribution. Email queues a PDF to listed addresses and/or handled-unit supervisors when MAIL_* is configured; otherwise download still works.'],
                            ['Handle shift swaps (if enabled)', 'Employees may request same-day swaps under Self Service → My Shift Swaps. Schedulers approve under Schedule → Shift Swaps. Locked months cannot be swapped.'],
                            ['Lock the schedule', 'Click Lock after approval. Locked schedules become the official reference, sync to payroll DTR encodings, and should only be changed through approved correction procedures.'],
                            ['Import from NDOS (CNO cutover)', 'Open Schedule → Import / Pull (or Dashboard Pull from NDOS). Requires schedule.manage. Only approved status A is pulled; months import locked; every pull re-compares and updates mapped rows (identical → unchanged). Never auto-deletes local history or pushes DTR. Same as artisan schedule:sync-schedulev2.'],
                            ['Full NDOS backfill (destructive)', 'Clears schedule-owned payroll_scheduler tables then imports shift codes, units, groups, employee defaults, handled units, floaters/on-call pools, signatories. NDOS has no patterns/templates. CLI: schedule:backfill-schedulev2 --dry-run then --apply --force [--with-assignments]. UI requires typing BACKFILL. Never lock→DTR.'],
                            ['Employee self-service', 'Staff with self-service.schedule open My Schedule to view their approved/locked assignments for a selected month. Swaps appear when uses_swaps is on.'],
                            ['Use for payroll', 'Payroll users compare DTR records against approved schedules during DTR encoding, MRA review, and payroll generation.'],
                        ] as [$title, $description])
                            <div class="grid gap-3 sm:grid-cols-[48px_minmax(0,1fr)]">
                                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-sm font-semibold text-white">
                                    {{ $loop->iteration }}
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-950">{{ $title }}</h4>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="cutover" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">5. Cutover</p>
                    <h3 class="mt-1 text-lg font-semibold">Retiring NDOS / legacy HRIS usage</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Phase 9 dual-run and freeze steps are documented in
                        <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">docs/hris-cutover.md</code>.
                        Admins with cutover permission can open
                        <a href="{{ route('admin.cutover') }}" class="font-semibold text-blue-700 hover:underline">Settings → Cutover Status</a>
                        for live flag / sync status. Do not set
                        <code class="rounded bg-slate-100 px-1 text-xs">HRIS_CUTOVER_SCHEDULE</code>
                        until a Nursing (and optional non-CNO) lock→DTR pilot is evidenced.
                        <code class="rounded bg-slate-100 px-1 text-xs">reference projects/</code>
                        remain historical / read-only.
                    </p>
                </section>

                <section id="summary" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">6. Summary</p>
                    <h3 class="mt-1 text-lg font-semibold">Operating Checklist</h3>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <h4 class="text-sm font-semibold text-emerald-900">Before Scheduling</h4>
                            <p class="mt-2 text-sm leading-6 text-emerald-800">Keep shift codes, employee references, groups, staffing rules, and templates updated.</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <h4 class="text-sm font-semibold text-blue-900">During Scheduling</h4>
                            <p class="mt-2 text-sm leading-6 text-blue-800">Generate drafts, validate issues, adjust coverage, and secure review approval.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">After Approval</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Lock the official roster and use it as the basis for DTR and payroll checks.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </section>
</x-layouts.app>
