<div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
    <div class="flex flex-wrap items-end gap-2">
        <label class="min-w-72 flex-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Import tax inputs
            <input wire:model="{{ $fileModel }}" type="file" accept=".xlsx,.xls,.xlsm" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case">
        </label>
        <button wire:click="{{ $validateAction }}" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Validate Import</button>
        <button wire:click="{{ $templateAction }}" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Download Template</button>
        @if ($preview !== [])
            <button wire:click="{{ $confirmAction }}" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Apply Valid Rows</button>
        @endif
    </div>
    @error($fileModel) <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
    @if ($importMessage) <div class="mt-2 text-xs text-slate-600">{{ $importMessage }}</div> @endif
    @if ($preview !== [])
        <div class="mt-3 max-h-56 overflow-auto rounded-md border border-slate-200 bg-white">
            <table class="min-w-full text-xs">
                <thead class="sticky top-0 bg-slate-100 text-left"><tr><th class="p-2">Row</th><th class="p-2">Employee</th><th class="p-2">Validation</th></tr></thead>
                <tbody>
                    @foreach ($preview as $item)
                        <tr class="border-t border-slate-100">
                            <td class="p-2">{{ $item['row'] }}</td>
                            <td class="p-2">{{ $item['emp_id'] }} · {{ $item['employee_name'] }}</td>
                            <td class="p-2 {{ $item['valid'] ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $item['valid'] ? ($item['name_mismatch'] ? 'Valid · employee name differs from HRIS' : 'Valid') : implode(' ', $item['errors']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
