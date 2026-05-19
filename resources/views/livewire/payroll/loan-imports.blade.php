<section class="space-y-4 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Loan Due Imports</h2>
            <p class="text-sm text-slate-600">Upload the uniform loan or deduction template and review validation before payroll generation.</p>
        </div>
        <button type="button" wire:click="exportTemplate" wire:loading.attr="disabled" wire:target="exportTemplate" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70">
            Export Template
        </button>
    </div>

    <div wire:loading.flex wire:target="exportTemplate" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Preparing Template</h3>
                    <p class="mt-1 text-sm text-slate-600">Building the hidden employee records and validation lists.</p>
                </div>
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full w-2/3 animate-pulse rounded-full bg-blue-600"></div>
            </div>
            <p class="mt-3 text-xs text-slate-500">The download will start automatically when the workbook is ready.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <div class="space-y-4">
            <form wire:submit="import" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold">Import Loan Due File</h3>
                <div class="mt-4">
                    <label class="text-sm font-medium">Excel file</label>
                    <input wire:model="loanFile" type="file" accept=".xlsx,.xls,.csv" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('loanFile')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="import,loanFile" class="mt-4 w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="import">Import Loan Excel</span>
                    <span wire:loading wire:target="import">Importing...</span>
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold">Recent Imports</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($imports as $import)
                        <button type="button" wire:click="selectImport({{ $import->id }})" class="block w-full px-4 py-3 text-left text-sm hover:bg-slate-50 {{ $selected?->id === $import->id ? 'bg-blue-50' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900">{{ $import->original_filename }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $import->source_entity }} · {{ optional($import->billing_period)->format('M Y') ?? 'No period' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $import->invalid_rows ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $import->invalid_rows ? 'Review' : 'Ready' }}
                                </span>
                            </div>
                            <div class="mt-2 flex gap-3 text-xs text-slate-500">
                                <span>{{ number_format($import->valid_rows) }} valid</span>
                                <span>{{ number_format($import->invalid_rows) }} invalid</span>
                                <span>{{ $import->imported_at?->format('M d, Y g:i A') }}</span>
                            </div>
                        </button>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-slate-500">No loan imports yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">Validation Grid</h3>
                    <p class="text-sm text-slate-600">
                        @if ($selected)
                            {{ $selected->original_filename }} · {{ number_format($selected->items->count()) }} row(s)
                        @else
                            Select an import to preview rows.
                        @endif
                    </p>
                </div>
                @if ($selected)
                    <div class="grid grid-cols-3 overflow-hidden rounded-md border border-slate-200 text-center text-xs">
                        <div class="px-3 py-2">
                            <p class="font-semibold">{{ number_format($selected->total_rows) }}</p>
                            <p class="text-slate-500">Rows</p>
                        </div>
                        <div class="border-l border-slate-200 px-3 py-2">
                            <p class="font-semibold text-emerald-700">{{ number_format($selected->valid_rows) }}</p>
                            <p class="text-slate-500">Valid</p>
                        </div>
                        <div class="border-l border-slate-200 px-3 py-2">
                            <p class="font-semibold text-amber-700">{{ number_format($selected->invalid_rows) }}</p>
                            <p class="text-slate-500">Invalid</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="max-h-[680px] overflow-auto">
                <table class="min-w-[1320px] border-separate border-spacing-0 text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                        <tr>
                            <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Row</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Entity</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Due Month</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee ID</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee Name</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Reference/Account No.</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amortization</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amount Due</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Balance</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($selected?->items ?? collect()) as $item)
                            <tr class="{{ $item->validation_status === 'valid' ? 'bg-white hover:bg-emerald-50/50' : 'bg-amber-50 hover:bg-amber-100/60' }}">
                                <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2 font-mono text-xs">{{ $item->row_number }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->validation_status === 'valid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($item->validation_status) }}
                                    </span>
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item->entity }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item->due_month?->format('Y-m') }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item->employee_id ?: $item->matched_emp_id }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-medium">{{ $item->employee_name }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item->loan_account_no }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ number_format((float) $item->monthly_amortization, 2) }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format((float) $item->amount_due, 2) }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ $item->outstanding_balance !== null ? number_format((float) $item->outstanding_balance, 2) : '-' }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {{ $item->validation_errors ? implode(' ', $item->validation_errors) : 'Ready for payroll Step 5.' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">No rows to display.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
