<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('employees.index') }}" class="text-sm font-semibold text-[#696cff] hover:underline">← Employees</a>
            <h2 class="mt-2 text-xl font-semibold">{{ $employee->full_name }}</h2>
            <p class="text-sm text-slate-600">
                {{ $employee->emp_id }}
                @if ($usesV2)
                    <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">hris_v2</span>
                @else
                    <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700">legacy</span>
                @endif
            </p>
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
                    <button type="button" wire:click="openDeactivateModal"
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
                    @if ($usesV2)
                        <div>
                            <label class="text-sm font-medium">Emergency contact</label>
                            <input wire:model="emergency_contact_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium">Emergency number</label>
                            <input wire:model="emergency_contact_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    @endif
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
                    @if ($usesV2)
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium">Residential address</label>
                            <textarea wire:model="residential_address" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium">Permanent address</label>
                            <textarea wire:model="permanent_address" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        </div>
                    @endif
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
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Employment</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Department</dt><dd class="font-medium text-slate-800">{{ $departmentName ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Position</dt><dd class="font-medium text-slate-800">{{ $positionName ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Date hired</dt><dd class="font-medium text-slate-800">{{ optional($employee->date_hired)->format('Y-m-d') ?: '—' }}</dd></div>
                    @if ($usesV2 && ! $isActive)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Separated</dt><dd class="font-medium text-slate-800">{{ optional($employee->date_separated)->format('Y-m-d') ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Reason</dt><dd class="font-medium text-slate-800">{{ $employee->separation_reason ?: '—' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Contact</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    @if ($usesV2)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ $employee->contact?->email ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-slate-800">{{ $employee->contact?->mobile_no ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Telephone</dt><dd class="font-medium text-slate-800">{{ $employee->contact?->telephone_no ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Emergency</dt><dd class="font-medium text-slate-800">{{ $employee->contact?->emergency_contact_name ?: '—' }} {{ $employee->contact?->emergency_contact_no ? '(' . $employee->contact->emergency_contact_no . ')' : '' }}</dd></div>
                    @else
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="font-medium text-slate-800">{{ $employee->email ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Mobile</dt><dd class="font-medium text-slate-800">{{ $employee->mobile_no ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Telephone</dt><dd class="font-medium text-slate-800">{{ $employee->tel_no ?: '—' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Personal / government IDs</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                    @if ($usesV2)
                        <div><p class="text-slate-500">Birthdate</p><p class="font-medium">{{ optional($employee->personal?->birthdate)->format('Y-m-d') ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Birthplace</p><p class="font-medium">{{ $employee->personal?->birthplace ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Sex</p><p class="font-medium">{{ $employee->personal?->sex ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Civil status</p><p class="font-medium">{{ $employee->personal?->civil_status ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Citizenship</p><p class="font-medium">{{ $employee->personal?->citizenship ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Religion</p><p class="font-medium">{{ $employee->personal?->religion ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Blood type</p><p class="font-medium">{{ $employee->personal?->blood_type ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Height</p><p class="font-medium">{{ $employee->personal?->height !== null ? $employee->personal->height.' m' : '—' }}</p></div>
                        <div><p class="text-slate-500">Weight</p><p class="font-medium">{{ $employee->personal?->weight !== null ? $employee->personal->weight.' kg' : '—' }}</p></div>
                        <div class="sm:col-span-2"><p class="text-slate-500">Residential</p><p class="font-medium">{{ $employee->personal?->residential_address ?: '—' }}</p></div>
                        <div class="sm:col-span-2"><p class="text-slate-500">Permanent</p><p class="font-medium">{{ $employee->personal?->permanent_address ?: '—' }}</p></div>
                        <div><p class="text-slate-500">TIN</p><p class="font-medium">{{ $employee->governmentIds?->tin_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">GSIS</p><p class="font-medium">{{ $employee->governmentIds?->gsis_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Pag-IBIG</p><p class="font-medium">{{ $employee->governmentIds?->pagibig_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">PhilHealth</p><p class="font-medium">{{ $employee->governmentIds?->phic_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">SSS</p><p class="font-medium">{{ $employee->governmentIds?->sss_no ?: '—' }}</p></div>
                        <div><p class="text-slate-500">Issued ID</p><p class="font-medium">{{ $employee->governmentIds?->issued_id_type ?: '—' }} {{ $employee->governmentIds?->issued_id_no ? '(' . $employee->governmentIds->issued_id_no . ')' : '' }}</p></div>
                        <div><p class="text-slate-500">ID date/place</p><p class="font-medium">{{ $employee->governmentIds?->issued_id_date_place ?: '—' }}</p></div>
                    @else
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
                    @endif
                </div>
            </section>
        </div>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">PDS sections</h3>
            <livewire:employees.employee-pds-sections :emp-id="$empId" :key="'pds-'.$empId" />
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Documents</h3>
            <livewire:employees.employee-documents :emp-id="$empId" :key="'docs-'.$empId" />
        </section>
    @endif

    @if ($showDeactivateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" wire:click.self="closeDeactivateModal">
            <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-lg">
                <h3 class="text-lg font-semibold text-slate-900">Deactivate employee</h3>
                <p class="mt-1 text-sm text-slate-600">Marks {{ $employee->full_name }} inactive for HRIS workflows.</p>

                <div class="mt-4 space-y-3">
                    @if ($usesV2)
                        <div>
                            <label class="text-sm font-medium">Separation date</label>
                            <input wire:model="date_separated" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-sm font-medium">Reason</label>
                            <input wire:model="separation_reason" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Resignation, end of contract, …">
                            @error('separation_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="closeDeactivateModal" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Cancel</button>
                    <button type="button" wire:click="deactivate" class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-500">Deactivate</button>
                </div>
            </div>
        </div>
    @endif
</div>
