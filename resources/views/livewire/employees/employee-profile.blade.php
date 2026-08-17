<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-[#696cff] hover:underline">← Employees</a>
            <h2 class="mt-2 text-xl font-semibold">{{ $employee->full_name }}</h2>
            <p class="text-sm text-slate-600">{{ $employee->emp_id }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($isActive)
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Inactive</span>
            @endif

            <a href="{{ route('employees.print', $employee->emp_id) }}" target="_blank"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Print PDS
            </a>

            @if ($canManage && ! $editing)
                <button type="button" wire:click="startEditing"
                        class="rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">
                    Edit profile
                </button>
                @if ($isActive)
                    <button type="button" x-on:click="erpOverlay.open($wire, 'employee-deactivate')"
                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-100">
                        Deactivate
                    </button>
                @else
                    <button type="button" wire:click="activate"
                            class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                        Reactivate
                    </button>
                @endif
            @endif
        </div>
    </div>

    {{-- Always-visible summary strip --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Employment</h3>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Department</dt><dd class="font-medium text-right text-slate-800">{{ $departmentName ?: '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Position</dt><dd class="font-medium text-right text-slate-800">{{ $positionName ?: '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Hired</dt><dd class="font-medium text-right text-slate-800">{{ optional($employee->date_hired)->format('Y-m-d') ?: '—' }}</dd></div>
            </dl>
        </section>
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Contact</h3>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Email</dt><dd class="max-w-[12rem] truncate font-medium text-right text-slate-800" title="{{ $employee->email }}">{{ $employee->email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-right text-slate-800">{{ $employee->mobile_no ?: '—' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Tel</dt><dd class="font-medium text-right text-slate-800">{{ $employee->tel_no ?: '—' }}</dd></div>
            </dl>
        </section>
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Vacation leave</h3>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($employee->vacation_leave_credits ?? 0), 3) }}</p>
            <p class="mt-1 text-xs text-slate-500">As of {{ optional($employee->date_gain_lc)->format('Y-m-d') ?: '—' }}</p>
        </section>
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Sick leave</h3>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($employee->sick_leave_credits ?? 0), 3) }}</p>
            <p class="mt-1 text-xs text-slate-500">Credits on employee record</p>
        </section>
    </div>

    {{-- Tab bar --}}
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex flex-wrap gap-1" aria-label="Employee hub tabs">
            @foreach ($tabs as $key => $label)
                <button type="button"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition',
                            'border-[#696cff] text-[#696cff]' => $tab === $key,
                            'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-800' => $tab !== $key,
                        ])>
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="min-h-[12rem]">
        @if ($tab === 'profile')
            @if ($editing)
                <form wire:submit="save" class="space-y-4">
                    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Identity</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                                <label class="text-sm font-medium">Date hired</label>
                                <input wire:model="date_hired" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Contact</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
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
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Personal</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium">Birthdate</label>
                                <input wire:model="birthdate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Birthplace</label>
                                <input wire:model="birthplace" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Sex</label>
                                <select wire:model="sex" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                                    <option value="">—</option>
                                    <option value="M">Male</option>
                                    <option value="F">Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium">Civil status</label>
                                <input wire:model="civil_status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Citizenship</label>
                                <input wire:model="citizenship" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Religion</label>
                                <input wire:model="religion" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Blood type</label>
                                <input wire:model="blood_type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Height (m)</label>
                                <input wire:model="height" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Weight (kg)</label>
                                <input wire:model="weight" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Government IDs</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="text-sm font-medium">TIN</label>
                                <input wire:model="tin_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">GSIS</label>
                                <input wire:model="gsis_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Pag-IBIG</label>
                                <input wire:model="pagibig_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">PhilHealth</label>
                                <input wire:model="phic_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">SSS</label>
                                <input wire:model="sss_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Government issued ID (type)</label>
                                <input wire:model="issued_id_type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Passport / UMID / Driver’s License / …">
                            </div>
                            <div>
                                <label class="text-sm font-medium">ID number</label>
                                <input wire:model="issued_id_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-sm font-medium">Date / place of issuance</label>
                                <input wire:model="issued_id_date_place" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </div>
                        </div>
                    </section>

                    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">PDS questions (CS Form 212)</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                'is_related_third_degree' => 'Related within 3rd degree to appointing authority?',
                                'is_related_fourth_degree' => 'Related within 4th degree (LGU career)?',
                                'is_admin_offense' => 'Found guilty of administrative offense?',
                                'is_criminally_charged' => 'Criminally charged before any court?',
                                'is_convicted' => 'Convicted of any crime / ordinance?',
                                'is_separated_service' => 'Separated from service (any mode)?',
                                'is_election_candidate' => 'Candidate in national/local election (last year)?',
                                'is_resigned_for_campaign' => 'Resigned to campaign (3 months before election)?',
                                'is_immigrant' => 'Immigrant / permanent resident of another country?',
                                'is_indigenous' => 'Member of indigenous group?',
                                'is_pwd' => 'Person with disability?',
                                'is_solo_parent' => 'Solo parent?',
                            ] as $field => $label)
                                <div>
                                    <label class="text-sm font-medium">{{ $label }}</label>
                                    <select wire:model="{{ $field }}" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                                        <option value="">—</option>
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <div class="flex gap-2">
                        <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-medium text-white hover:bg-[#5f61e6]">Save</button>
                        <button type="button" wire:click="cancelEditing" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
                    </div>
                </form>
            @else
                <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Personal / government IDs</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                        <div><p class="text-slate-500">Birthdate</p><p class="font-medium">{{ optional($employee->birthdate)->format('Y-m-d') ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Birthplace</p><p class="font-medium">{{ $employee->birthplace ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Sex</p><p class="font-medium">{{ $employee->gender ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Civil status</p><p class="font-medium">{{ $employee->civil_stat ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Height</p><p class="font-medium">{{ $employee->height !== null ? $employee->height.' m' : '—' }}</p></div>
                        <div><p class="text-slate-500">Weight</p><p class="font-medium">{{ $employee->weight !== null ? $employee->weight.' kg' : '—' }}</p></div>
                        <div><p class="text-slate-500">Blood type</p><p class="font-medium">{{ $employee->blood_type ?: '—' }}</p></div>
                        <div><p class="text-slate-500">TIN</p><p class="font-medium">{{ $employee->tin_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">GSIS</p><p class="font-medium">{{ $employee->gsis_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Pag-IBIG</p><p class="font-medium">{{ $employee->pagibig_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">PhilHealth</p><p class="font-medium">{{ $employee->phic_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">SSS</p><p class="font-medium">{{ $employee->sss_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Issued ID</p><p class="font-medium">{{ $employee->gov_id ?: '—' }} {{ $employee->govid_no ? '(' . $employee->govid_no . ')' : '' }}</p></div>
                    </div>
                </section>
            @endif
        @elseif ($tab === 'employment')
            <livewire:employees.employee-employment-history-panel :emp-id="$empId" :key="'employment-'.$empId" />
        @elseif ($tab === 'pds')
            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <livewire:employees.employee-pds-sections :emp-id="$empId" :key="'pds-'.$empId" />
            </section>
        @elseif ($tab === 'documents')
            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <livewire:employees.employee-documents :emp-id="$empId" :key="'docs-'.$empId" />
            </section>
        @elseif ($tab === 'leave')
            <livewire:employees.employee-leave-panel :emp-id="$empId" :key="'leave-'.$empId" />
        @elseif ($tab === 'training')
            <livewire:employees.employee-training-panel :emp-id="$empId" :key="'training-'.$empId" />
        @elseif ($tab === 'ipcr')
            <livewire:employees.employee-ipcr-panel :emp-id="$empId" :key="'ipcr-'.$empId" />
        @elseif ($tab === 'dtr')
            <livewire:employees.employee-dtr-panel :emp-id="$empId" :key="'dtr-'.$empId" />
        @elseif ($tab === 'biometrics')
            <livewire:employees.employee-fingerprint-panel :emp-id="$empId" :key="'biometrics-'.$empId" />
        @elseif ($tab === 'schedule')
            <livewire:employees.employee-schedule-panel :emp-id="$empId" :key="'schedule-'.$empId" />
        @elseif ($tab === 'payroll')
            <livewire:employees.employee-payroll-panel :emp-id="$empId" :key="'payroll-'.$empId" />
        @elseif ($tab === 'account')
            <livewire:employees.employee-account-panel :emp-id="$empId" :key="'account-'.$empId" />
        @endif
    </div>

    <x-setup-form-modal name="employee-deactivate" title="Deactivate employee" size="sm">
        <p class="text-sm text-slate-600">Marks {{ $employee->full_name }} inactive for HRIS workflows.</p>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" x-on:click="erpOverlay.close('employee-deactivate')" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Cancel</button>
            <button type="button" wire:click="deactivate" class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-500">Deactivate</button>
        </div>
    </x-setup-form-modal>
</div>
