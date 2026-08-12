<section class="space-y-4 pb-24">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Medicare Payroll</h2>
            <p class="text-sm text-slate-600">
                {{ $scopeName }} · {{ Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }} · {{ $employeeTypeLabel }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Doctors' professional fees for {{ $professionalFeePeriod['start']->format('F Y') }}
                taxed as {{ $taxRule['name'] }} via {{ str_replace('_', ' ', $taxRule['tax_treatment']) }}.
            </p>
        </div>

        <a href="{{ $this->configurationRoute() }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Change Configuration
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-sm font-medium">Doctor Search</label>
        <input wire:model.live.debounce.500ms="search" type="search" placeholder="Filter by employee ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($steps as $number => $label)
                <button
                    type="button"
                    wire:click="goToStep({{ $number }})"
                    class="rounded-md border px-3 py-2 text-left text-sm transition {{ $currentStep === $number ? 'border-[#5f61e6] bg-[#5f61e6] font-semibold text-white shadow-sm shadow-[#696cff]/25' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <span class="block text-xs font-semibold uppercase tracking-wide">Step {{ $number }}</span>
                    <span class="mt-1 block font-medium">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($currentStep === 1)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Previous Month Professional Fees</h3>
                <p class="text-sm text-slate-600">
                    PF Period: {{ $professionalFeePeriod['start']->format('M d, Y') }} to {{ $professionalFeePeriod['end']->format('M d, Y') }}.
                    Enter or import gross professional fees, then continue to review tax and net pay.
                </p>
            </div>

            @include('livewire.payroll.partials.tax-input-import', [
                'importLabel' => 'Import professional fees',
                'fileModel' => 'professionalFeeFile',
                'preview' => $professionalFeeImportPreview,
                'importMessage' => $professionalFeeImportMessage,
                'validateAction' => 'previewProfessionalFeeImport',
                'templateAction' => 'exportProfessionalFeeTemplate',
                'confirmAction' => 'confirmProfessionalFeeImport',
            ])

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
                            <th class="px-4 py-3 text-right">Adjustment</th>
                            <th class="px-4 py-3 text-right">Adjusted Gross</th>
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
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model.blur="professionalFees.{{ $row['emp_id'] }}"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-32 rounded-md border border-slate-300 px-2 py-1 text-right text-sm"
                                    >
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model.blur="adjustments.{{ $row['emp_id'] }}"
                                        type="number"
                                        step="0.01"
                                        class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm"
                                    >
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['adjusted_gross_professional_fees'], 2) }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700' => $row['adjusted_gross_professional_fees'] > 0,
                                        'bg-amber-50 text-amber-700' => $row['adjusted_gross_professional_fees'] <= 0,
                                    ])>
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                                    @if (! $divisionId && ! $departmentId)
                                        Choose a division or department from Payroll Configuration to load doctor rows.
                                    @else
                                        No doctor/medical officer rows found for this configuration yet.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="5" class="px-4 py-3">Totals</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['gross_professional_fees'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['adjustment'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['adjusted_gross_professional_fees'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Review</h3>
                <p class="text-sm text-slate-600">
                    Withholding uses Compensation Rules tax treatment for Medicare
                    (default supplemental flat rate {{ rtrim(rtrim(number_format(($taxRule['supplemental_tax_rate'] ?? 0.15) * 100, 2), '0'), '.') }}%).
                    Finalize/save to payroll history is not wired yet — use this screen for computation and verification.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1280px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee No.</th>
                            <th class="px-4 py-3">Doctor / Employee</th>
                            <th class="px-4 py-3">Position</th>
                            <th class="px-4 py-3 text-right">Adjusted Gross PF</th>
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
                                    <div class="text-xs text-slate-500">{{ $row['department'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $row['position'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['adjusted_gross_professional_fees'], 2) }}</td>
                                <td class="px-4 py-3">{{ $row['tax_treatment'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['withholding_tax'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_medicare_pay'], 2) }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700' => $row['adjusted_gross_professional_fees'] > 0,
                                        'bg-amber-50 text-amber-700' => $row['adjusted_gross_professional_fees'] <= 0,
                                    ])>
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                    No doctor/medical officer rows found for this configuration yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="3" class="px-4 py-3">Totals</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['adjusted_gross_professional_fees'], 2) }}</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_medicare_pay'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-30 flex justify-center px-4">
        <div class="pointer-events-auto flex items-center gap-3 rounded-lg border border-white/50 bg-white/70 px-3 py-2 shadow-lg shadow-slate-900/10 backdrop-blur-md">
            <button type="button" wire:click="previousStep" @disabled($currentStep === 1) class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50">
                Previous
            </button>
            <div class="min-w-20 text-center text-sm text-slate-700">Step {{ $currentStep }} of {{ count($steps) }}</div>
            <button type="button" wire:click="nextStep" @disabled($currentStep === count($steps)) class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50">
                Next
            </button>
        </div>
    </div>
</section>
