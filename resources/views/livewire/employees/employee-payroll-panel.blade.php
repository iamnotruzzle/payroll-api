<div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Payroll</h3>
            <p class="text-sm text-slate-600">Recent batch records and related deductions for this employee.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['payroll.view', 'payroll.generate', 'payroll.approve'])
                <a href="{{ route('payroll.history') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Payroll history
                </a>
            @endcanany
            @canany(['self-service.payslip', 'self-service.access'])
                <a href="{{ route('self-service.payslip') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    My Payslip
                </a>
            @endcanany
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Batch records <span class="font-normal text-slate-500">({{ $records->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Period</th>
                        <th class="px-4 py-2 font-semibold">Type</th>
                        <th class="px-4 py-2 font-semibold">Gross</th>
                        <th class="px-4 py-2 font-semibold">Net</th>
                        <th class="px-4 py-2 font-semibold text-right">Payslip</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($records as $record)
                        <tr wire:key="hub-pay-{{ $record->id }}">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $record->batch?->payroll_period ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $record->batch?->payroll_type ?: ($record->batch?->payroll_type_code ?: '—') }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $record->gross, 2) }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $record->net, 2) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <a href="{{ route('payroll.history.payslip.print', $record->id) }}" target="_blank"
                                   class="text-sm font-medium text-[#696cff] hover:underline">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No payroll batch records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($loans->isNotEmpty())
        <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-slate-800">Loan import items <span class="font-normal text-slate-500">({{ $loans->count() }})</span></h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-semibold">Type</th>
                            <th class="px-4 py-2 font-semibold">Account</th>
                            <th class="px-4 py-2 font-semibold">Due month</th>
                            <th class="px-4 py-2 font-semibold">Amount due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($loans as $loan)
                            <tr wire:key="hub-loan-{{ $loan->id }}">
                                <td class="px-4 py-2.5 text-slate-800">{{ $loan->loan_type ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $loan->loan_account_no ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ optional($loan->due_month)->format('Y-m') ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $loan->amount_due, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($deductionMembers->isNotEmpty())
        <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
                <h4 class="text-sm font-semibold text-slate-800">Deduction program memberships <span class="font-normal text-slate-500">({{ $deductionMembers->count() }})</span></h4>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-semibold">Program ID</th>
                            <th class="px-4 py-2 font-semibold">Employee name</th>
                            <th class="px-4 py-2 font-semibold">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($deductionMembers as $member)
                            <tr wire:key="hub-ded-{{ $member->id }}">
                                <td class="px-4 py-2.5 text-slate-800">{{ $member->deduction_program_id }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $member->employee_name ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $member->source ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
