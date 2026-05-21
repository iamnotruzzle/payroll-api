<x-layouts.app title="Medicare Payroll">
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold">Medicare Payroll</h2>
                <p class="text-sm text-slate-600">Dedicated Medicare payroll workflow.</p>
            </div>
            <a href="{{ route('payroll.generation.configuration', request()->only(['division_id', 'department_id', 'period', 'working_days', 'employee_type']) + ['payroll_type' => \App\Models\Payroll\PayrollType::CODE_MEDICARE]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Change Configuration
            </a>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            Medicare payroll has its own page and can now receive the selected payroll configuration.
        </div>
    </section>
</x-layouts.app>
