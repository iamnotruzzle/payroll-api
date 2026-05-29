<x-layouts.app title="Payroll Operations User Manual">
    <section class="mx-auto max-w-7xl space-y-6">
        <div class="border-b border-slate-200 pb-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">User Manual</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Payroll Operations</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Use this guide to prepare DTR records, finalize MRA, maintain compensation and deduction references,
                import loan deductions, generate payroll, review payroll results, and view finalized payroll history.
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
                        <a href="#generation-steps" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">5. Generation Steps</a>
                        <a href="#summary" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">6. Summary</a>
                    </div>
                </nav>
            </aside>

            <div class="space-y-6">
                <section id="flowchart" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">1. Flowchart</p>
                            <h3 class="mt-1 text-lg font-semibold">Payroll Operations Process</h3>
                        </div>
                        <span class="rounded-md bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">DTR to Payroll History</span>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                        @foreach ([
                            ['Prepare References', 'Maintain compensation rules, deduction programs, loan references, holidays, and employee payroll data.'],
                            ['Encode DTR', 'Label blank weekdays, assign schedules, and review tardiness or undertime per employee.'],
                            ['Finalize MRA', 'Generate the Monthly Report of Attendance for leave, undertime, tardiness, and day-equivalent deductions.'],
                            ['Import Deductions', 'Upload loan due files, validate rows, and keep only clean imported deductions for payroll use.'],
                            ['Generate Payroll', 'Choose scope, review each payroll step, save drafts as needed, and finalize the run.'],
                            ['Review History', 'Open finalized snapshots to verify payroll period, payroll type, deductions, tax, and net pay.'],
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
                    <h3 class="mt-1 text-lg font-semibold">Recommended Baseline Configuration</h3>

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
                                    <td class="px-3 py-3 font-medium">Compensation Rules</td>
                                    <td class="px-3 py-3">Basic Salary, PERA, Subsistence, Laundry, Hazard, Medicare</td>
                                    <td class="px-3 py-3 text-slate-600">Defines earnings included in regular and special payroll computations.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Deduction Programs</td>
                                    <td class="px-3 py-3">Recurring office deductions, fixed employee deductions, special one-time deductions</td>
                                    <td class="px-3 py-3 text-slate-600">Controls program deductions that can be applied during payroll generation.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Loan References</td>
                                    <td class="px-3 py-3">GSIS, Pag-IBIG, cooperative, aid, account number, deduction type</td>
                                    <td class="px-3 py-3 text-slate-600">Maps imported loan due rows into the correct payroll deduction columns.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">DTR Labels</td>
                                    <td class="px-3 py-3">Leave with pay, leave without pay, official business, no DTR, holiday</td>
                                    <td class="px-3 py-3 text-slate-600">Explains blank workdays and determines whether payroll can compute schedules and exceptions.</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-3 font-medium">Payroll Scope</td>
                                    <td class="px-3 py-3">Payroll type, division, department, employee type, month, working days</td>
                                    <td class="px-3 py-3 text-slate-600">Defines which employees and period are included in the payroll run.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="use-cases" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">3. Use Cases</p>
                    <h3 class="mt-1 text-lg font-semibold">Common Payroll Operations Scenarios</h3>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ([
                            ['Monthly regular payroll', 'Payroll staff complete DTR encoding, finalize MRA, review regular payroll steps, export the template if needed, and finalize the payroll run.'],
                            ['Missing DTR records', 'Blank weekdays are labeled first. Once labels are complete, schedules can be encoded and tardiness or undertime can be computed.'],
                            ['Loan due processing', 'A loan due file is exported, filled in, imported, validated, and then included as imported deductions during payroll generation.'],
                            ['Payroll audit review', 'After finalization, Payroll History provides payroll snapshots for checking earnings, statutory deductions, tax, other deductions, and final net pay.'],
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
                    <h3 class="mt-1 text-lg font-semibold">How to Use Payroll Operations</h3>

                    <div class="mt-5 space-y-5">
                        @foreach ([
                            ['Review payroll references', 'Open Compensation Rules, Deduction Programs, Loan References, and Holidays. Confirm active records, formulas, tax treatment, and reference mappings before payroll processing.'],
                            ['Encode DTR labels', 'Open DTR Encoding, select month, year, employee type, and employee. Label blank weekdays before applying schedules. Save after reviewing each employee.'],
                            ['Apply schedules and validate exceptions', 'After required labels are complete, choose the correct schedule template for eligible days. Review computed tardiness and undertime before saving.'],
                            ['Finalize MRA', 'Open MRA, choose the target month, year, and employee type. Review leave, undertime, tardiness, and physically reported hours, then click Finalize MRA.'],
                            ['Prepare loan due imports', 'Open Loan Due Imports, export the template, encode due month, employee, reference or account number, amortization, amount due, and balance, then import the completed file.'],
                            ['Resolve invalid loan rows', 'Use the Validation Grid to identify missing employees, invalid references, wrong periods, or invalid amounts. Correct the source file and re-import until the file is ready.'],
                            ['Choose payroll configuration', 'Open Payroll Generation, select payroll type, employee type, division, optional department, payroll month, and working days, then proceed to generation.'],
                            ['Review payroll generation steps', 'Move through MRA validation, allowances, adjustments, statutory deductions, deduction programs, imported deductions, tax calculation, and final review.'],
                            ['Save drafts while reviewing', 'Use Save as Draft when payroll review is not yet complete. The draft keeps overrides, compensation adjustments, and deduction program selections for the same configuration.'],
                            ['Finalize and verify history', 'From the Review step, export the regular payroll template if needed, then finalize the payroll run. Open Payroll History to view the saved snapshot.'],
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

                <section id="generation-steps" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">5. Generation Steps</p>
                    <h3 class="mt-1 text-lg font-semibold">Regular Payroll Generation Review</h3>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ([
                            ['MRA Validation', 'Confirms prior finalized MRA adjustments and deduction days used as payroll input.'],
                            ['Allowances Computation', 'Displays configured earnings and gross pay based on employee payroll data and compensation rules.'],
                            ['Deductions and Adjustments', 'Allows manual compensation adjustments with remarks before net compensation is computed.'],
                            ['Statutory', 'Reviews employee and government shares for mandatory deductions such as life retirement, PhilHealth, and Pag-IBIG.'],
                            ['Deduction Programs', 'Applies configured deduction programs that should reduce employee net pay.'],
                            ['Imported Deductions', 'Displays validated imported loan and other deduction rows matched to the payroll month.'],
                            ['Tax Calculation', 'Reviews annualized taxable income, tax due, regular tax, supplemental tax, and withholding tax.'],
                            ['Review', 'Shows the final payroll snapshot before export and finalization.'],
                        ] as [$title, $description])
                            <article class="rounded-lg border border-slate-200 p-4">
                                <h4 class="text-sm font-semibold text-slate-950">{{ $loop->iteration }}. {{ $title }}</h4>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="summary" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">6. Summary</p>
                    <h3 class="mt-1 text-lg font-semibold">Operating Checklist</h3>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <h4 class="text-sm font-semibold text-emerald-900">Before Payroll</h4>
                            <p class="mt-2 text-sm leading-6 text-emerald-800">Confirm employee payroll data, references, holidays, compensation rules, deduction programs, and loan mappings.</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <h4 class="text-sm font-semibold text-blue-900">During Payroll</h4>
                            <p class="mt-2 text-sm leading-6 text-blue-800">Complete DTR labels, schedules, MRA finalization, loan import validation, generation review, and draft saving.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">After Payroll</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Finalize the run, export required files, and use Payroll History snapshots for audit and verification.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </section>
</x-layouts.app>
