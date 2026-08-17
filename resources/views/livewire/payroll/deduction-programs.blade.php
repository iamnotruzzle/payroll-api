<section class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Deduction Programs</h2>
            <p class="text-sm text-slate-600">Manage reusable deductions such as Death Aid, cooperative dues, association dues, and similar payroll programs.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'deduction-program', { editingId: null, name: '', computationType: 'fixed', value: 0, sortOrder: 0, insertAfterColumn: '', section: 'other', impactType: 'employee_deduction', isRecurring: true, isActive: true })"
            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600"
        >
            New Program
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="font-semibold">Programs</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3 text-right">Value</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr wire:key="deduction-program-management-{{ $item->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $item->name }}</div>
                                <div class="text-xs text-slate-500">Sort {{ (int) ($item->sort_order ?? 0) }}</div>
                                <div class="text-xs text-slate-500">{{ str($item->section ?? 'other')->title() }} · {{ str($item->impact_type ?? 'employee_deduction')->replace('_', ' ')->title() }} · {{ $item->is_recurring ? 'Recurring' : 'Per payroll' }}</div>
                                @if ($item->insert_after_column)
                                    <div class="text-xs text-blue-600">Placed after {{ str($item->insert_after_column)->replace('_', ' ')->title() }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->is_percentage ? 'Percentage' : 'Fixed amount' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $item->value, 4) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    x-on:click="erpOverlay.open($wire, 'deduction-program', { editingId: {{ $item->id }}, name: @js($item->name), computationType: @js($item->is_percentage ? 'percentage' : 'fixed'), value: {{ (float) $item->value }}, sortOrder: {{ (int) ($item->sort_order ?? 0) }}, insertAfterColumn: @js((string) ($item->insert_after_column ?? '')), section: @js((string) ($item->section ?? 'other')), impactType: @js((string) ($item->impact_type ?? 'employee_deduction')), isRecurring: @js((bool) ($item->is_recurring ?? true)), isActive: @js((bool) $item->is_active) }, true)"
                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                                >Edit</button>
                                <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="Delete this deduction program?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No deduction programs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-setup-form-drawer name="deduction-program" title="New Program" edit-title="Edit Program" description="Configure how this deduction appears in payroll generation." size="lg">
            <form wire:submit.prevent="save" class="space-y-4">
                <div>
                    <label class="text-sm font-medium">Payroll section</label>
                    <select wire:model="section" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="mandatory">Mandatory Deductions</option>
                        <option value="other">Other Deductions → Others</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Payroll impact</label>
                    <select wire:model="impactType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="employee_deduction">Employee deduction</option>
                        <option value="employer_contribution">Employer contribution / display only</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input wire:model="isRecurring" type="checkbox" class="rounded border-slate-300"> Recurring by default
                </label>
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model="name" type="text" placeholder="Program name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Computation</label>
                    <select wire:model="computationType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percentage">Percentage of basic salary</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Value</label>
                    <input wire:model="value" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">For percentage, use 25 or 0.25 for 25%.</p>
                    @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium">
                        <input wire:model="isActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
                <div>
                    <label class="text-sm font-medium">Generated column position</label>
                    <select wire:model="insertAfterColumn" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Deduction Programs section (default)</option>
                        <option value="life_retirement">After GSIS (PS)</option>
                        <option value="government_life_retirement">After GSIS (GS)</option>
                        <option value="phic">After PHIC (PS)</option>
                        <option value="government_phic">After PHIC (GS)</option>
                        <option value="mandatory_pagibig">After HDMF (PS) 1</option>
                        <option value="hdmf_ps_2_ms">After HDMF (PS) 2 MS</option>
                        <option value="government_pagibig">After HDMF (GS)</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" x-on:click="erpOverlay.close('deduction-program')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600" x-text="editing ? 'Update Program' : 'Save Program'">
                        Save Program
                    </button>
                </div>
            </form>
        </x-setup-form-drawer>
</section>
