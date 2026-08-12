<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-[#696cff] hover:underline">← Employees</a>
            <h2 class="mt-2 text-xl font-semibold">Add employee</h2>
            <p class="text-sm text-slate-600">Create a workforce record on legacy HRIS. PDS sections can be completed on the profile page.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-4">
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Identity</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Employee number</label>
                    <input wire:model="emp_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 001234">
                    @error('emp_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">First name</label>
                    <input wire:model="firstname" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('firstname') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Middle name</label>
                    <input wire:model="middlename" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Last name</label>
                    <input wire:model="lastname" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('lastname') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Prefix</label>
                    <input wire:model="prefix" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Suffix / extension</label>
                    <input wire:model="extension" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Birth date</label>
                    <input wire:model="birthdate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('birthdate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Sex</label>
                    <select wire:model="sex" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Date hired</label>
                    <input wire:model="date_hired" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Employment</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Department</label>
                    <select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Position</label>
                    <select wire:model="position_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->position_id }}">{{ $position->position_title }}</option>
                        @endforeach
                    </select>
                    @error('position_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Employment status</label>
                    <select wire:model="empstat_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select</option>
                        @foreach ($employmentStatuses as $status)
                            <option value="{{ $status->empstat_id }}">{{ $status->status }}</option>
                        @endforeach
                    </select>
                    @error('empstat_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Contact</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Email</label>
                    <input wire:model="email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Mobile</label>
                    <input wire:model="mobile_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Telephone</label>
                    <input wire:model="telephone_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <label class="flex items-start gap-3 text-sm">
                <input wire:model="provision_account" type="checkbox" class="mt-1 rounded border-slate-300">
                <span>
                    <span class="font-medium text-slate-800">Create login account</span>
                    <span class="mt-0.5 block text-slate-600">Username = employee number, Employee role, temporary password shown once, first-login profile update required.</span>
                </span>
            </label>
        </section>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Create employee</button>
            <a href="{{ route('employees.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
