<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Payroll Generation</h2>
            <p class="text-sm text-slate-600">Preview employees available from HRIS before creating a payroll run.</p>
        </div>
        <a href="{{ route('payroll.compensations') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Manage Compensations
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[2fr_1fr_1fr_1fr]">
            <div>
                <label class="text-sm font-medium">Department</label>
                <select wire:model.live="departmentId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Choose department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Period</label>
                <input wire:model.live="period" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Working Days</label>
                <input wire:model.live="workingDays" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Search</label>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Emp ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employees</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($rows->count()) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic Salary</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totals['basic_salary'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gross Preview</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totals['gross'], 2) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="font-semibold">HRIS Payroll Preview</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Emp ID</th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3 text-right">SG</th>
                        <th class="px-4 py-3 text-right">Step</th>
                        <th class="px-4 py-3 text-right">Leave/Deduct Days</th>
                        <th class="px-4 py-3 text-right">Basic Salary</th>
                        <th class="px-4 py-3 text-right">Subsistence</th>
                        <th class="px-4 py-3 text-right">PERA</th>
                        <th class="px-4 py-3 text-right">Laundry</th>
                        @foreach ($compensations as $item)
                            <th class="px-4 py-3 text-right">{{ $item->name }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-right">Life Retirement</th>
                        <th class="px-4 py-3 text-right">PHIC</th>
                        <th class="px-4 py-3 text-right">Mandatory Pag-IBIG</th>
                        <th class="px-4 py-3 text-right">Gross</th>
                        <th class="px-4 py-3 text-right">Net Preview</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium">{{ $row['emp_id'] }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['department'] }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['position'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['salary_grade'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['step'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['deduction_days'], 3) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['basic_salary'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['standard_compensations']['subsistence'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['standard_compensations']['pera'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['standard_compensations']['laundry'], 2) }}</td>
                            @foreach ($compensations as $item)
                                <td class="px-4 py-3 text-right">{{ number_format($row['compensations'][$item->id]['amount'] ?? 0, 2) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['life_retirement'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['phic'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['gross'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 15 + $compensations->count() }}" class="px-4 py-8 text-center text-slate-500">
                                No active HRIS employees found for the selected department.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="6" class="px-4 py-3">Totals</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['standard_compensations']['subsistence'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['standard_compensations']['pera'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['standard_compensations']['laundry'], 2) }}</td>
                            @foreach ($compensations as $item)
                                <td class="px-4 py-3 text-right">{{ number_format($totals['compensations'][$item->id] ?? 0, 2) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['life_retirement'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['phic'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['gross'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['net_before_other_deductions'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
        HRIS lookup stays focused on employee, position, salary grade, and step. Payroll amounts are computed in the app from those fields, matching the formulas from your reference query.
    </div>
</section>
