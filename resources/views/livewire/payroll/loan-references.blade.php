<section class="space-y-4 pb-12">
    <div @class(['hidden' => $isAdditionalPremiumMode])>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
            <h2 class="text-xl font-semibold">{{ $isAdditionalPremiumMode ? 'Additional Premiums' : 'Loan References' }}</h2>
            <p class="text-sm text-slate-600">{{ $isAdditionalPremiumMode ? 'Manage employee savings and premium deduction types used by payroll generation imports.' : 'Select a loan entity, then manage loan types and review-column mappings for imported loan files.' }}</p>
            </div>
            @if ($isAdditionalPremiumMode)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('payroll.loan-imports.template') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Export Import Template</a>
                    <a href="{{ route('payroll.loan-imports') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Open Imports</a>
                </div>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div @class([
        'grid gap-4',
        'xl:grid-cols-[320px_minmax(0,1fr)]' => ! $isAdditionalPremiumMode,
    ])>
        <div @class([
            'overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
            'hidden' => $isAdditionalPremiumMode,
        ])>
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">{{ $isAdditionalPremiumMode ? 'Category' : 'Entities' }}</h3>
                @unless ($isAdditionalPremiumMode)
                    <button type="button" x-on:click="erpOverlay.open($wire, 'loan-entity', { editingEntityId: null, entityCode: '', entityName: '', entitySortOrder: 0, entityIsActive: true })" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                        New Entity
                    </button>
                @endunless
            </div>
            <div class="max-h-[720px] divide-y divide-slate-100 overflow-y-auto">
                @forelse ($entities as $entity)
                    <button type="button" wire:click="selectEntity({{ $entity->id }})" class="block w-full px-4 py-3 text-left text-sm hover:bg-slate-50 {{ $selectedEntity?->id === $entity->id ? 'bg-blue-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900">{{ $entity->code }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $entity->name }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $entity->loan_types_count }}</span>
                        </div>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-500">No loan entities yet.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $isAdditionalPremiumMode ? 'Premium Type Setup' : 'Selected Entity' }}</p>
                        <h3 class="mt-1 text-lg font-semibold">{{ $isAdditionalPremiumMode ? 'Additional Premium Types' : ($selectedEntity?->code ?? 'No entity selected') }}</h3>
                        <p class="text-sm text-slate-600">{{ $isAdditionalPremiumMode ? 'Maintain type names, review columns, and import matching keywords.' : $selectedEntity?->name }}</p>
                    </div>
                    @if ($selectedEntity)
                        <div class="flex gap-2">
                            <button type="button" x-on:click="erpOverlay.open($wire, 'loan-type', { editingTypeId: null, typeEntityId: {{ $selectedEntity->id }}, typeCode: '', typeName: '', reviewGroup: @js($isAdditionalPremiumMode ? 'Additional Premiums' : 'Bank Loans'), reviewColumnKey: @js($isAdditionalPremiumMode ? 'additional_premium' : ''), reviewColumnLabel: @js($isAdditionalPremiumMode ? 'Additional Premium' : ''), insertAfterColumn: '', matchKeywords: '', typeSortOrder: 0, typeIsActive: true })" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                New Type
                            </button>
                            @unless ($isAdditionalPremiumMode)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'loan-entity', { editingEntityId: {{ $selectedEntity->id }}, entityCode: @js($selectedEntity->code), entityName: @js($selectedEntity->name), entitySortOrder: {{ (int) $selectedEntity->sort_order }}, entityIsActive: @js((bool) $selectedEntity->is_active) }, true)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                    Edit Entity
                                </button>
                            @endunless
                        </div>
                    @endif
                </div>
            </div>

            @if ($selectedEntity)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h3 class="font-semibold">{{ $isAdditionalPremiumMode ? 'Configured Premium Types' : $selectedEntity->code.' Types' }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Review Column</th>
                                    <th class="px-4 py-3">Keywords</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($types as $type)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ $type->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $type->code }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ $type->review_group }} / {{ $type->review_column_label }}</div>
                                            <div class="font-mono text-xs text-slate-500">{{ $type->review_column_key }}</div>
                                            @if ($type->insert_after_column)
                                                <div class="text-xs text-blue-600">After {{ str($type->insert_after_column)->replace('_', ' ')->title() }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600">{{ implode(', ', $type->match_keywords ?: []) ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $type->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" x-on:click="erpOverlay.open($wire, 'loan-type', { editingTypeId: {{ $type->id }}, typeEntityId: {{ $type->entity_id }}, typeCode: @js($type->code), typeName: @js($type->name), reviewGroup: @js($type->review_group), reviewColumnKey: @js($type->review_column_key), reviewColumnLabel: @js($type->review_column_label), insertAfterColumn: @js((string) ($type->insert_after_column ?? '')), matchKeywords: @js(implode(', ', $type->match_keywords ?: [])), typeSortOrder: {{ (int) $type->sort_order }}, typeIsActive: @js((bool) $type->is_active) }, true)" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
                                            <button wire:click="deleteType({{ $type->id }})" wire:confirm="Delete this type?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No types for {{ $selectedEntity->code }} yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">
                    Create or select an entity to manage its types.
                </div>
            @endif
        </div>
    </div>

    <x-setup-form-modal name="loan-entity" title="New Entity" edit-title="Edit Entity" size="sm">
            <form wire:submit="saveEntity" class="space-y-3">
                <div>
                    <label class="text-sm font-medium">Code</label>
                    <input wire:model="entityCode" type="text" placeholder="GSIS" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('entityCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model="entityName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('entityName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model="entitySortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium">
                        <input wire:model="entityIsActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" x-show="editing" x-cloak x-on:click="confirm('Delete this entity and its types?') && $wire.deleteEntity($wire.editingEntityId)" class="mr-auto rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                    <button type="button" x-on:click="erpOverlay.close('loan-entity')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Entity</button>
                </div>
            </form>
        </x-setup-form-modal>

        <x-setup-form-drawer
            name="loan-type"
            title="New Type{{ $selectedEntity ? ' for '.$selectedEntity->code : '' }}"
            edit-title="Edit Type"
            size="lg"
        >
            <form wire:submit="saveType" class="space-y-4">
                <input type="hidden" wire:model="typeEntityId">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Code</label>
                        <input wire:model="typeCode" type="text" placeholder="MPL" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('typeCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model="typeSortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Name</label>
                        <input wire:model="typeName" type="text" placeholder="Multi-Purpose Loan" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('typeName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Review Group</label>
                        <input wire:model="reviewGroup" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('reviewGroup') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Column Key</label>
                        <input wire:model="reviewColumnKey" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('reviewColumnKey') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Column Label</label>
                        <input wire:model="reviewColumnLabel" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('reviewColumnLabel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium">Match Keywords</label>
                    <input wire:model="matchKeywords" type="text" placeholder="MPL, CONSO, MULTI-PURPOSE" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Comma-separated words used to classify imported rows into Review columns.</p>
                </div>
                <div>
                    <label class="text-sm font-medium">Generated column position</label>
                    <select wire:model="insertAfterColumn" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Own section (default)</option>
                        <option value="life_retirement">After GSIS (PS)</option>
                        <option value="government_life_retirement">After GSIS (GS)</option>
                        <option value="phic">After PHIC (PS)</option>
                        <option value="government_phic">After PHIC (GS)</option>
                        <option value="mandatory_pagibig">After HDMF (PS) 1</option>
                        <option value="government_pagibig">After HDMF (GS)</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Moves this premium/deduction column beside the selected generated-payroll column.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input wire:model="typeIsActive" type="checkbox" class="rounded border-slate-300">
                    Active
                </label>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" x-show="editing" x-cloak x-on:click="confirm('Delete this type?') && $wire.deleteType($wire.editingTypeId)" class="mr-auto rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                    <button type="button" x-on:click="erpOverlay.close('loan-type')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Type</button>
                </div>
            </form>
        </x-setup-form-drawer>
</section>
