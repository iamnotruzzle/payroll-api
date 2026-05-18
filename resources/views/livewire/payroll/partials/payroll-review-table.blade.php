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
                @foreach ($compensations as $item)
                    <th class="px-4 py-3 text-right">{{ $item->name }}</th>
                @endforeach
                <th class="px-4 py-3 text-right">Life Retirement</th>
                <th class="px-4 py-3 text-right">PHIC</th>
                <th class="px-4 py-3 text-right">Mandatory Pag-IBIG</th>
                <th class="px-4 py-3 text-right">Gross</th>
                <th class="px-4 py-3 text-right">Net Preview</th>
                <th class="px-4 py-3 text-right">15th</th>
                <th class="px-4 py-3 text-right">30th</th>
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
                    @foreach ($compensations as $item)
                        <td class="px-4 py-3 text-right">{{ number_format($row['compensations'][$item->id]['amount'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['life_retirement'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['phic'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['gross'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['fifteenth'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['thirtieth'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 14 + $compensations->count() }}" class="px-4 py-8 text-center text-slate-500">
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
                    @foreach ($compensations as $item)
                        <td class="px-4 py-3 text-right">{{ number_format($totals['compensations'][$item->id] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['life_retirement'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['phic'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['gross'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['net_before_other_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['fifteenth'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['thirtieth'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
