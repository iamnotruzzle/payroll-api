<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Employees</h2>
            <p class="text-sm text-slate-600">
                Directory of workforce records
                @if ($usesV2)
                    <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">hris_v2</span>
                @else
                    <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">legacy</span>
                @endif
            </p>
        </div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-[#696cff] hover:underline">All apps</a>
    </div>

    @unless ($usesV2)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Reading legacy HRIS. Run <code class="rounded bg-white px-1">php artisan hris:migrate-employees --apply</code>, validate, then set <code class="rounded bg-white px-1">HRIS_USE_V2=true</code>.
        </div>
    @endunless

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search employees</span>
                <input wire:model.live.debounce.300ms="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search name or employee ID">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Status
                <select wire:model.live="status" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="all">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Rows
                <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="20">20</option>
                    <option value="40">40</option>
                    <option value="60">60</option>
                </select>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-left">Position</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    @php
                        $active = \App\Support\Hris\EmployeeDirectoryQuery::isActive($employee);
                        $department = \App\Support\Hris\EmployeeDirectoryQuery::departmentName($employee);
                        $position = \App\Support\Hris\EmployeeDirectoryQuery::positionName($employee);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $employee->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $employee->emp_id }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $department ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $position ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($active)
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Active</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employees.show', $employee->emp_id) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No employees found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>
        {{ $employees->links() }}
    </div>
</div>
