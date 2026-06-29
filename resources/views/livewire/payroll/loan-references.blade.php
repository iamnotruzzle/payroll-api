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
                    <button type="button" wire:click="openEntityModal" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
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
                            <button type="button" wire:click="openTypeModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                New Type
                            </button>
                            @unless ($isAdditionalPremiumMode)
                                <button type="button" wire:click="openEntityModal({{ $selectedEntity->id }})" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
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
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600">{{ implode(', ', $type->match_keywords ?: []) ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $type->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button wire:click="openTypeModal({{ $type->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
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

    @if ($showEntityModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 class="font-semibold">{{ $editingEntityId ? 'Edit Entity' : 'New Entity' }}</h3>
                    <button type="button" wire:click="closeEntityModal" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100">&times;</button>
                </div>
                <form wire:submit="saveEntity" class="space-y-3 px-5 py-5">
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
                        @if ($editingEntityId)
                            <button type="button" wire:click="deleteEntity({{ $editingEntityId }})" wire:confirm="Delete this entity and its types?" class="mr-auto rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                        @endif
                        <button type="button" wire:click="closeEntityModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Entity</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showTypeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h3 class="font-semibold">{{ $editingTypeId ? 'Edit Type' : 'New Type for '.$selectedEntity?->code }}</h3>
                    <button type="button" wire:click="closeTypeModal" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100">&times;</button>
                </div>
                <form wire:submit="saveType" class="space-y-4 px-5 py-5">
                    <input type="hidden" wire:model="typeEntityId">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <label class="text-sm font-medium">Code</label>
                            <input wire:model="typeCode" type="text" placeholder="MPL" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('typeCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium">Name</label>
                            <input wire:model="typeName" type="text" placeholder="Multi-Purpose Loan" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('typeName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium">Sort</label>
                            <input wire:model="typeSortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
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
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        @if ($editingTypeId)
                            <button type="button" wire:click="deleteType({{ $editingTypeId }})" wire:confirm="Delete this type?" class="mr-auto rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                        @endif
                        <label class="mr-auto flex items-center gap-2 text-sm font-medium">
                            <input wire:model="typeIsActive" type="checkbox" class="rounded border-slate-300">
                            Active
                        </label>
                        <button type="button" wire:click="closeTypeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Type</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
