<x-layouts.app title="Additional Premiums">
    <div
        x-data="{ tab: 'imports' }"
        class="space-y-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">Additional Premiums</h2>
                <p class="text-sm text-slate-600">Manage premium deductions, import files, and type mappings in one workspace.</p>
            </div>
            <div class="inline-flex overflow-hidden rounded-md border border-slate-200 bg-white p-1 shadow-sm">
                <button
                    type="button"
                    x-on:click="tab = 'imports'"
                    class="rounded px-4 py-2 text-sm font-medium transition"
                    x-bind:class="tab === 'imports' ? 'bg-[#5f61e6] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                >
                    Imports
                </button>
                <button
                    type="button"
                    x-on:click="tab = 'setup'"
                    class="rounded px-4 py-2 text-sm font-medium transition"
                    x-bind:class="tab === 'setup' ? 'bg-[#5f61e6] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                >
                    Premium Types
                </button>
            </div>
        </div>

        <div x-show="tab === 'imports'">
            <livewire:payroll.loan-imports mode="additional_premiums" />
        </div>

        <div x-cloak x-show="tab === 'setup'">
            <livewire:payroll.loan-references mode="additional_premiums" />
        </div>
    </div>
</x-layouts.app>
