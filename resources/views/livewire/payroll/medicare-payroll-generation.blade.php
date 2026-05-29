<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Medicare Payroll</h2>
            <p class="text-sm text-slate-600">
                {{ $scopeName }} · {{ Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Placeholder workflow. Medicare is expected to use doctors' professional fees from
                {{ $professionalFeePeriod['start']->format('F Y') }}.
            </p>
        </div>

        <a href="{{ $this->configurationRoute() }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Change Configuration
        </a>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Computation is intentionally not implemented yet. The source, rate, tax treatment, and approval flow for Medicare professional fees still need confirmation.
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-sm font-medium">Doctor Search</label>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Filter placeholder rows by employee ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="font-semibold">Previous Month Professional Fees Placeholder</h3>
            <p class="text-sm text-slate-600">
                PF Period: {{ $professionalFeePeriod['start']->format('M d, Y') }} to {{ $professionalFeePeriod['end']->format('M d, Y') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1280px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee No.</th>
                        <th class="px-4 py-3">Doctor / Employee</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">PF Period</th>
                        <th class="px-4 py-3 text-right">Gross Professional Fees</th>
                        <th class="px-4 py-3">Tax Treatment</th>
                        <th class="px-4 py-3 text-right">Withholding Tax</th>
                        <th class="px-4 py-3 text-right">Net Medicare Pay</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium">{{ $row['emp_id'] }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['division'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['position'] ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $row['department'] ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $row['professional_fee_period'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">TBD</td>
                            <td class="px-4 py-3">{{ $row['tax_treatment'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">TBD</td>
                            <td class="px-4 py-3 text-right text-slate-400">TBD</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-slate-500">
                                No doctor/medical officer rows found for this configuration yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
