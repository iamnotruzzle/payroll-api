<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My Profile</h2>
            <p class="text-sm text-slate-600">
                {{ $employee->full_name }} · {{ $employee->emp_id }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($isActive)
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Inactive</span>
            @endif
            <a href="{{ route('self-service.profile.print') }}" target="_blank"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Print PDS
            </a>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Employment</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Department</dt><dd class="font-medium text-slate-800">{{ $departmentName ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Position</dt><dd class="font-medium text-slate-800">{{ $positionName ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Date hired</dt><dd class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayDate($employee->date_hired ?? null) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Contact</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($employee->email) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($employee->mobile_no) }}</dd></div>
            </dl>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Personal / government IDs</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div><p class="text-slate-500">Birthdate</p><p class="font-medium">{{ \App\Livewire\SelfService\MyProfile::displayDate($employee->birthdate ?? null) }}</p></div>
                <div><p class="text-slate-500">Sex</p><p class="font-medium">{{ $sexLabel !== '' ? $sexLabel : '—' }}</p></div>
                <div><p class="text-slate-500">Civil status</p><p class="font-medium">{{ $civilStatusLabel !== '' ? $civilStatusLabel : '—' }}</p></div>
                <div><p class="text-slate-500">TIN</p><p class="font-medium">{{ \App\Livewire\SelfService\MyProfile::displayValue($employee->tin_no) }}</p></div>
                <div><p class="text-slate-500">GSIS</p><p class="font-medium">{{ \App\Livewire\SelfService\MyProfile::displayValue($employee->gsis_no) }}</p></div>
                <div><p class="text-slate-500">PhilHealth</p><p class="font-medium">{{ \App\Livewire\SelfService\MyProfile::displayValue($employee->phic_no) }}</p></div>
            </div>
            <p class="mt-4 text-xs text-slate-500">Contact HR if any of this information needs correction.</p>
        </section>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">PDS sections</h3>
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach ($sectionLabels as $key => $label)
                <button type="button"
                        wire:click="setSection('{{ $key }}')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium {{ $section === $key ? 'bg-[#696cff] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($section === 'other_infos')
            <div class="space-y-4">
                @forelse ($otherInfoGroups as $group)
                    <div class="overflow-hidden rounded-md border border-slate-200">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                            {{ $group['label'] }}
                        </div>
                        @if ($group['items']->isEmpty())
                            <p class="px-4 py-4 text-sm text-slate-500">No records.</p>
                        @else
                            <ul class="divide-y divide-slate-100 text-sm">
                                @foreach ($group['items'] as $row)
                                    <li class="px-4 py-3 text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->title) }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <div class="rounded-md border border-slate-200 px-4 py-8 text-center text-sm text-slate-500">No records in this section.</div>
                @endforelse
            </div>
        @else
            {{-- Two-row-per-record list: R1 identity, R2 muted detail grid --}}
            <div class="overflow-hidden rounded-md border border-slate-200">
                <ul class="divide-y divide-slate-200 text-sm">
                    @forelse ($rows as $row)
                        <li wire:key="ss-pds-{{ $section }}-{{ $row->id }}" class="px-4 py-3">
                            @if ($section === 'dependents')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->label) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="text-slate-600">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->relationship ?? null) }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span><span class="text-slate-400">Birthdate</span> {{ \App\Livewire\SelfService\MyProfile::displayDate($row->birthdate ?? null) }}</span>
                                    <span><span class="text-slate-400">Gender</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->sex_label ?? $row->sex ?? null) }}</span>
                                </div>

                            @elseif ($section === 'educations')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->education_level_label ?? $row->education_level ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="break-words text-slate-700">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->school ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="break-words text-slate-600">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->education_title ?? null) }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span><span class="text-slate-400">From</span> {{ \App\Livewire\SelfService\MyProfile::displayDate($row->start_date ?? null) }}</span>
                                    <span><span class="text-slate-400">To</span> {{ \App\Livewire\SelfService\MyProfile::displayDate($row->end_date ?? null) }}</span>
                                    <span><span class="text-slate-400">Year graduated</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->year_graduated ?? null) }}</span>
                                    <span class="break-words"><span class="text-slate-400">Honors</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->honors ?? null) }}</span>
                                </div>

                            @elseif ($section === 'eligibilities')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="break-words font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->title ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="text-slate-600">Rating {{ \App\Livewire\SelfService\MyProfile::displayValue($row->rating ?? null, 'N/A') }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span><span class="text-slate-400">Exam date</span> {{ \App\Livewire\SelfService\MyProfile::displayDate($row->confer_date ?? null) }}</span>
                                    <span class="break-words"><span class="text-slate-400">Place</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->confer_place ?? null) }}</span>
                                    <span><span class="text-slate-400">Number</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->license_no ?? null, 'N/A') }}</span>
                                    <span><span class="text-slate-400">Validity</span> {{ \App\Livewire\SelfService\MyProfile::displayDate($row->exp_date ?? null, 'N/A') }}</span>
                                </div>

                            @elseif ($section === 'work_experiences')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="break-words font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->work_position ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="text-slate-600">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->work_status ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="break-words text-slate-700">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->company_name ?? null) }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span class="break-words"><span class="text-slate-400">Address</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->company_address ?? null) }}</span>
                                    <span><span class="text-slate-400">Salary</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->salary ?? null) }}</span>
                                    <span>
                                        <span class="text-slate-400">Period</span>
                                        {{ \App\Livewire\SelfService\MyProfile::displayDate($row->start_date ?? null) }}
                                        –
                                        @if (empty($row->end_date))
                                            To present
                                        @else
                                            {{ \App\Livewire\SelfService\MyProfile::displayDate($row->end_date) }}
                                        @endif
                                    </span>
                                    <span><span class="text-slate-400">Government</span> {{ ! empty($row->is_government) ? 'Yes' : 'No' }}</span>
                                </div>

                            @elseif ($section === 'trainings')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="break-words font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->training_name ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="text-slate-600">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->hours ?? null) }} hrs</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span class="break-words"><span class="text-slate-400">Venue</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->training_venue ?? null) }}</span>
                                    <span class="break-words"><span class="text-slate-400">Sponsor</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->sponsor ?? null) }}</span>
                                    <span><span class="text-slate-400">Date</span> {{ \App\Livewire\SelfService\MyProfile::displayDateRange($row->start_date ?? null, $row->end_date ?? null) }}</span>
                                    <span><span class="text-slate-400">LDI</span> —</span>
                                </div>

                            @elseif ($section === 'voluntary_works')
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                    <span class="break-words font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->organization_name ?? null) }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="break-words text-slate-600">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->position ?? null) }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span><span class="text-slate-400">Dates</span> {{ \App\Livewire\SelfService\MyProfile::displayDateRange($row->start_date ?? null, $row->end_date ?? null) }}</span>
                                    <span><span class="text-slate-400">Hours</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->hours ?? null) }}</span>
                                </div>

                            @elseif ($section === 'references')
                                <div class="font-medium text-slate-800">{{ \App\Livewire\SelfService\MyProfile::displayValue($row->name ?? null) }}</div>
                                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span class="break-words"><span class="text-slate-400">Address</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->address ?? null) }}</span>
                                    <span><span class="text-slate-400">Contact</span> {{ \App\Livewire\SelfService\MyProfile::displayValue($row->telephone_no ?? null) }}</span>
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-slate-500">No records in this section.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </section>
</div>
