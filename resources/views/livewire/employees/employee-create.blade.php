@php
    $steps = [1 => 'Personal', 2 => 'Employment', 3 => 'Contact & Account', 4 => 'Review', 5 => 'Biometrics'];
    $departmentName = $departments->firstWhere('department_id', $department_id)?->department;
    $positionName = $positions->firstWhere('position_id', $position_id)?->position_title;
    $statusName = $employmentStatuses->firstWhere('empstat_id', $empstat_id)?->status;
@endphp

<div class="space-y-5">
    <div>
        <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-[#696cff] hover:underline">&larr; Employees</a>
        <h2 class="mt-2 text-xl font-semibold">Add employee</h2>
        <p class="text-sm text-slate-600">Create the employee record, account, and biometric registration in one guided process.</p>
    </div>

    <ol class="grid overflow-hidden rounded-lg border border-slate-200 bg-white sm:grid-cols-5">
        @foreach($steps as $number => $label)
            <li class="border-b border-slate-200 p-3 last:border-0 sm:border-b-0 sm:border-r" @if($number < $step && !$createdEmpId) wire:click="goToStep({{ $number }})" role="button" @endif>
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold {{ $number <= $step ? 'bg-[#696cff] text-white' : 'bg-slate-100 text-slate-500' }}">{{ $number }}</span>
                    <span class="text-xs font-semibold {{ $number === $step ? 'text-[#696cff]' : 'text-slate-600' }}">{{ $label }}</span>
                </div>
            </li>
        @endforeach
    </ol>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Please correct the highlighted employee details.</p>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form wire:submit="{{ $step === 4 ? 'save' : 'nextStep' }}" class="space-y-4">
        @if($step === 1)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div><h3 class="font-semibold text-slate-900">Personal information</h3><p class="text-sm text-slate-500">Core identity details used throughout HRIS and payroll.</p></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="text-sm font-medium">Employee number <span class="text-red-500">*</span></label><input wire:model="emp_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 001234">@error('emp_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">First name <span class="text-red-500">*</span></label><input wire:model="firstname" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('firstname')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Middle name</label><input wire:model="middlename" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-sm font-medium">Last name <span class="text-red-500">*</span></label><input wire:model="lastname" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('lastname')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Prefix</label><input wire:model="prefix" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Dr., Atty."></div>
                    <div><label class="text-sm font-medium">Suffix / extension</label><input wire:model="extension" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Jr., III"></div>
                    <div><label class="text-sm font-medium">Birth date <span class="text-red-500">*</span></label><input wire:model="birthdate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('birthdate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Sex</label><select wire:model="sex" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Select</option><option value="M">Male</option><option value="F">Female</option></select></div>
                </div>
            </section>
        @elseif($step === 2)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div><h3 class="font-semibold text-slate-900">Employment assignment</h3><p class="text-sm text-slate-500">Initial organizational placement and employment status.</p></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="text-sm font-medium">Department <span class="text-red-500">*</span></label><select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->department_id }}">{{ $department->department }}</option>@endforeach</select>@error('department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Position <span class="text-red-500">*</span></label><select wire:model="position_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Select position</option>@foreach($positions as $position)<option value="{{ $position->position_id }}">{{ $position->position_title }}</option>@endforeach</select>@error('position_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Employment status <span class="text-red-500">*</span></label><select wire:model="empstat_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Select status</option>@foreach($employmentStatuses as $status)<option value="{{ $status->empstat_id }}">{{ $status->status }}</option>@endforeach</select>@error('empstat_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Date hired</label><input wire:model="date_hired" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('date_hired')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                </div>
            </section>
        @elseif($step === 3)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div><h3 class="font-semibold text-slate-900">Contact and account</h3><p class="text-sm text-slate-500">Contact channels and initial self-service access.</p></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><label class="text-sm font-medium">Email</label><input wire:model="email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="text-sm font-medium">Mobile</label><input wire:model="mobile_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                    <div><label class="text-sm font-medium">Telephone</label><input wire:model="telephone_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
                </div>
                <label class="mt-5 flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm"><input wire:model="provision_account" type="checkbox" class="mt-1 rounded border-slate-300"><span><span class="font-medium text-slate-800">Create login account</span><span class="mt-0.5 block text-slate-600">The employee number becomes the username. A temporary password is displayed once after creation.</span></span></label>
            </section>
        @elseif($step === 4)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div><h3 class="font-semibold text-slate-900">Review employee details</h3><p class="text-sm text-slate-500">Confirm the information below before creating the permanent employee record.</p></div>
                <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Employee</dt><dd class="mt-1 font-medium">{{ $emp_id }} · {{ trim("$firstname $middlename $lastname $extension") }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Birth date / Sex</dt><dd class="mt-1">{{ $birthdate ?: '—' }} · {{ $sex ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Date hired</dt><dd class="mt-1">{{ $date_hired ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Department</dt><dd class="mt-1">{{ $departmentName ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Position</dt><dd class="mt-1">{{ $positionName ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Employment status</dt><dd class="mt-1">{{ $statusName ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Email</dt><dd class="mt-1">{{ $email ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Contact</dt><dd class="mt-1">{{ $mobile_no ?: ($telephone_no ?: '—') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-slate-500">Login account</dt><dd class="mt-1">{{ $provision_account ? 'Create account' : 'Do not create' }}</dd></div>
                </dl>
            </section>
        @else
            <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900"><h3 class="font-semibold">Employee {{ $createdEmpId }} was created successfully.</h3>@if($temporaryPassword)<p class="mt-1 text-sm">Temporary password: <strong class="font-mono">{{ $temporaryPassword }}</strong>. Copy this now; it is shown only during this process.</p>@endif</section>
            @if(auth()->user()?->can('timekeeping.view') || auth()->user()?->can('timekeeping.manage'))
                <livewire:employees.employee-fingerprint-panel :emp-id="$createdEmpId" :key="'new-employee-biometrics-'.$createdEmpId" />
            @else
                <section class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-600">You do not have permission to manage biometric registration. An authorized timekeeping manager can enroll fingerprints from the employee profile.</section>
            @endif
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>@if($step > 1 && $step < 5)<button type="button" wire:click="previousStep" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50">Previous</button>@endif</div>
            <div class="flex gap-2">
                @if($step < 4)<button type="submit" class="rounded-md bg-[#696cff] px-5 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Continue</button>
                @elseif($step === 4)<button type="submit" wire:loading.attr="disabled" class="rounded-md bg-[#696cff] px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Create employee</span><span wire:loading wire:target="save">Creating...</span></button>
                @else<a href="{{ route('employees.show', $createdEmpId) }}" class="rounded-md bg-[#696cff] px-5 py-2 text-sm font-semibold text-white">Finish and view employee</a>@endif
                <a href="{{ route('employees.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ $step === 5 ? 'Return to directory' : 'Cancel' }}</a>
            </div>
        </div>
    </form>
</div>
