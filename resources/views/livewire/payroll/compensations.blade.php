<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Compensation Rules</h2>
        <p class="text-sm text-slate-600">Manage fixed, percentage, and formula-based compensation columns used in payroll previews.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[540px_minmax(0,1fr)]">
        <form wire:submit="save" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Rule' : 'New Rule' }}</h3>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model.blur="name" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Computation</label>
                    <select wire:model.change="computationType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percentage">Percentage of basic salary</option>
                        <option value="formula">Formula</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Value</label>
                    <input wire:model.blur="value" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">For percentage, use 25 or 0.25 for 25%.</p>
                    @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($computationType === 'formula')
                    <div
                        class="erp-formula-editor overflow-hidden rounded-md border border-slate-300 bg-white"
                        x-data="{
                            insert(text) {
                                const editor = this.$refs.editor;
                                const start = editor.selectionStart ?? editor.value.length;
                                const end = editor.selectionEnd ?? start;
                                editor.value = editor.value.slice(0, start) + text + editor.value.slice(end);
                                editor.dispatchEvent(new Event('input', { bubbles: true }));
                                this.$nextTick(() => {
                                    editor.focus();
                                    editor.setSelectionRange(start + text.length, start + text.length);
                                });
                            },
                            replace(text) {
                                const editor = this.$refs.editor;
                                editor.value = text;
                                editor.dispatchEvent(new Event('input', { bubbles: true }));
                                this.$nextTick(() => editor.focus());
                            },
                            wrap(prefix, suffix) {
                                const editor = this.$refs.editor;
                                const start = editor.selectionStart ?? editor.value.length;
                                const end = editor.selectionEnd ?? start;
                                const selected = editor.value.slice(start, end);
                                const text = prefix + selected + suffix;
                                editor.value = editor.value.slice(0, start) + text + editor.value.slice(end);
                                editor.dispatchEvent(new Event('input', { bubbles: true }));
                                this.$nextTick(() => {
                                    editor.focus();
                                    const caret = selected ? start + text.length : start + prefix.length;
                                    editor.setSelectionRange(caret, caret);
                                });
                            },
                        }"
                    >
                        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                            <label class="text-sm font-medium">Formula</label>
                            <span class="font-mono text-[11px] text-slate-500">Expression</span>
                        </div>

                        <textarea
                            x-ref="editor"
                            wire:model.blur="formula"
                            rows="4"
                            spellcheck="false"
                            placeholder="max(0, configured_value - 50 * subsistence_deduct_days)"
                            class="erp-formula-editor-input block w-full resize-y border-0 bg-slate-900 px-3 py-3 font-mono text-sm leading-6 text-slate-100 outline-none placeholder:text-slate-500"
                        ></textarea>

                        <div class="space-y-3 border-t border-slate-200 bg-white p-3">
                            <div>
                                <div class="mb-1.5 text-[11px] font-semibold uppercase text-slate-500">Variables</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ([
                                        'configured_value',
                                        'basic_salary',
                                        'working_days',
                                        'paid_days',
                                        'leave_days',
                                        'subsistence_deduct_days',
                                        'laundry_deduct_days',
                                        'pera_deduct_days',
                                        'tev_deduct_days',
                                        'hazard_rate',
                                        'is_part_time',
                                    ] as $variable)
                                        <button
                                            type="button"
                                            x-on:click="insert(@js($variable))"
                                            class="erp-formula-token rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-[11px] text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                                        >{{ $variable }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[auto_1fr]">
                                <div>
                                    <div class="mb-1.5 text-[11px] font-semibold uppercase text-slate-500">Operators</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ([' + ', ' - ', ' * ', ' / ', '(', ')'] as $operator)
                                            <button
                                                type="button"
                                                x-on:click="insert(@js($operator))"
                                                class="erp-formula-operator min-w-8 rounded border border-slate-200 bg-white px-2 py-1 font-mono text-xs text-slate-700 hover:border-blue-300 hover:bg-blue-50"
                                            >{{ $operator }}</button>
                                        @endforeach
                                        <button type="button" x-on:click="wrap('max(0, ', ')')" class="erp-formula-operator rounded border border-slate-200 bg-white px-2 py-1 font-mono text-xs text-slate-700 hover:border-blue-300 hover:bg-blue-50">max()</button>
                                        <button type="button" x-on:click="wrap('min(', ', 0)')" class="erp-formula-operator rounded border border-slate-200 bg-white px-2 py-1 font-mono text-xs text-slate-700 hover:border-blue-300 hover:bg-blue-50">min()</button>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-1.5 text-[11px] font-semibold uppercase text-slate-500">Templates</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" x-on:click="replace('max(0, configured_value - 50 * subsistence_deduct_days) * (1 - (0.5 * is_part_time))')" class="erp-formula-template rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Subsistence</button>
                                        <button type="button" x-on:click="replace('max(0, configured_value - 6.818 * laundry_deduct_days) * (1 - (0.5 * is_part_time))')" class="erp-formula-template rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Laundry</button>
                                        <button type="button" x-on:click="replace('basic_salary * hazard_rate')" class="erp-formula-template rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Hazard Pay</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @error('formula') <p class="border-t border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium">Variable Name</label>
                    <input wire:model.blur="variableName" type="text" placeholder="hazard_pay" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('variableName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div class="text-sm font-semibold">Tax Treatment</div>
                    <p class="mt-1 text-xs text-slate-600">Controls whether this item affects net pay and how it enters withholding tax.</p>

                    <label class="mt-3 flex items-center gap-2 text-sm font-medium">
                        <input wire:model.change="includeInNetPay" type="checkbox" class="rounded border-slate-300">
                        Include in employee net pay
                    </label>

                    <div class="mt-3">
                        <label class="text-sm font-medium">Treatment</label>
                        <select wire:model.change="taxTreatment" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="regular_taxable">Regular taxable, annualized</option>
                            <option value="non_taxable">Non-taxable</option>
                            <option value="de_minimis_annual_limit">De minimis with annual exempt limit</option>
                            <option value="supplemental_flat_rate">Supplemental flat tax rate</option>
                        </select>
                        @error('taxTreatment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($taxTreatment === 'de_minimis_annual_limit')
                        <div class="mt-3">
                            <label class="text-sm font-medium">Annual Exempt Limit</label>
                            <input wire:model.blur="annualExemptLimit" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('annualExemptLimit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($taxTreatment === 'supplemental_flat_rate')
                        <div class="mt-3">
                            <label class="text-sm font-medium">Supplemental Tax Rate</label>
                            <input wire:model.blur="supplementalTaxRate" type="number" step="0.0001" min="0" max="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Use 0.15 for 15%.</p>
                            @error('supplementalTaxRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model.blur="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium">
                        <input wire:model.change="isActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                    Save Rule
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Cancel
                    </button>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Rules</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Value</th>
                            <th class="px-4 py-3">Formula</th>
                            <th class="px-4 py-3">Tax Treatment</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->variable_name ?: str($item->name)->snake() }}</div>
                                </td>
                                <td class="px-4 py-3">{{ ucfirst($item->computation_type ?: ($item->is_percentage ? 'percentage' : 'fixed')) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->value, 4) }}</td>
                                <td class="max-w-[420px] px-4 py-3 font-mono text-xs break-words">{{ $item->formula ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-xs font-medium">{{ str($item->tax_treatment ?: 'regular_taxable')->replace('_', ' ')->title() }}</div>
                                    <div class="text-xs text-slate-500">{{ ($item->include_in_net_pay ?? true) ? 'Included in net pay' : 'Tax only / reference' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="edit({{ $item->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this compensation rule?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No compensation rules yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
