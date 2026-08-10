<div class="space-y-4">
    @if (session('pds_status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('pds_status') }}</div>
    @endif

    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        @foreach ($sectionLabels as $key => $label)
            <button type="button"
                    wire:click="setSection('{{ $key }}')"
                    class="rounded-md px-3 py-1.5 text-sm font-medium {{ $section === $key ? 'bg-[#696cff] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ $sectionLabels[$section] ?? $section }}</h3>
        @if ($canManage && ! $editing)
            <button type="button" wire:click="startCreate"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50">
                Add
            </button>
        @endif
    </div>

    @if ($editing)
        <form wire:submit="save" class="space-y-3 rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="font-semibold text-slate-800">{{ $editingId ? 'Edit record' : 'New record' }}</h4>

            @if ($section === 'dependents')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Last name</label>
                        <input wire:model="lastname" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('lastname') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
                        <label class="text-sm font-medium">Name extension</label>
                        <input wire:model="extension" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Jr., Sr., III">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Relationship</label>
                        <select wire:model="relationship" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            <option value="SPOUSE">Spouse</option>
                            <option value="WIFE">Wife</option>
                            <option value="HUSBAND">Husband</option>
                            <option value="FATHER">Father</option>
                            <option value="MOTHER">Mother</option>
                            <option value="CHILD">Child</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Birthdate</label>
                        <input wire:model="birthdate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
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
                        <label class="text-sm font-medium">Occupation</label>
                        <input wire:model="occupation" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Employer / business name</label>
                        <input wire:model="employer_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Business address</label>
                        <input wire:model="employer_address" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Telephone</label>
                        <input wire:model="telephone_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            @elseif ($section === 'educations')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Level of education</label>
                        <select wire:model="education_level" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            @foreach ($educationLevelOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Basic education / degree / course</label>
                        <input wire:model="education_title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Name of school</label>
                        <input wire:model="school" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">From</label>
                        <input wire:model="start_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">To</label>
                        <input wire:model="end_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Year graduated</label>
                        <input wire:model="year_graduated" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Highest level / units earned</label>
                        <input wire:model="units" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Scholarship / academic honors</label>
                        <input wire:model="honors" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Attachment URL</label>
                        <input wire:model="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional">
                    </div>
                </div>
            @elseif ($section === 'eligibilities')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Eligibility (lookup)</label>
                        <select wire:model="eligibility_lookup_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            @foreach ($eligibilityOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Title override</label>
                        <input wire:model="title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional if lookup set">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Rating</label>
                        <input wire:model="rating" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Date of conferment / exam</label>
                        <input wire:model="confer_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Place of examination / conferment</label>
                        <input wire:model="confer_place" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">License / certificate no.</label>
                        <input wire:model="license_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Date of validity</label>
                        <input wire:model="exp_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            @elseif ($section === 'work_experiences')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Position title</label>
                        <input wire:model="work_position" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Department / agency / office / company</label>
                        <input wire:model="company_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium">Company address</label>
                        <input wire:model="company_address" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Status of appointment</label>
                        <select wire:model="work_status_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            @foreach ($employmentStatusOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">From</label>
                        <input wire:model="start_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">To</label>
                        <input wire:model="end_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Monthly salary</label>
                        <input wire:model="salary" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Salary / job / pay grade</label>
                        <input wire:model="salary_grade" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Step increment</label>
                        <input wire:model="step_inc" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="mt-6 flex items-center gap-2 text-sm">
                        <input wire:model="is_government" type="checkbox"> Government service (Y/N)
                    </label>
                </div>
            @elseif ($section === 'trainings')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium">Title of L&amp;D / training program</label>
                        <input wire:model="training_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">From</label>
                        <input wire:model="start_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">To</label>
                        <input wire:model="end_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Number of hours</label>
                        <input wire:model="hours" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Type of L&amp;D (lookup)</label>
                        <select wire:model="type_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            @foreach ($trainingTypeOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Type name</label>
                        <input wire:model="type_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Managerial / Supervisory / Technical / …">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Conducted / sponsored by</label>
                        <input wire:model="sponsor" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Venue</label>
                        <input wire:model="training_venue" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Attachment URL</label>
                        <input wire:model="url" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional">
                    </div>
                </div>
            @elseif ($section === 'voluntary_works')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="text-sm font-medium">Name &amp; address of organization</label>
                        <input wire:model="organization_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Include address in this field if needed">
                    </div>
                    <div>
                        <label class="text-sm font-medium">From</label>
                        <input wire:model="start_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">To</label>
                        <input wire:model="end_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Number of hours</label>
                        <input wire:model="hours" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Position / nature of work</label>
                        <input wire:model="position" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            @elseif ($section === 'other_infos')
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Category</label>
                        <select wire:model="type" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                            <option value="">—</option>
                            @foreach ($otherInfoTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Matches CS Form 212 other-information groups.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Title / description</label>
                        <input wire:model="title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            @elseif ($section === 'references')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Name</label>
                        <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Office / residential address</label>
                        <input wire:model="address" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Contact no. and/or email</label>
                        <input wire:model="telephone_no" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-medium text-white hover:bg-[#5f61e6]">Save</button>
                <button type="button" wire:click="cancelEdit" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
            </div>
        </form>
    @endif

    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="w-max min-w-full table-auto divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    @if ($section === 'dependents')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Name</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Ext.</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Relationship</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Birthdate</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Sex</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Occupation</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Employer</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Address</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Tel.</th>
                    @elseif ($section === 'educations')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Level</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">School</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Course / degree</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">From</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">To</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Units</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Year grad.</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Honors</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">URL</th>
                    @elseif ($section === 'eligibilities')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Eligibility</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Rating</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Date</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Place</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">License no.</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Valid until</th>
                    @elseif ($section === 'work_experiences')
                        <th class="whitespace-nowrap px-3 py-3 text-left">From</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">To</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Position</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Company / agency</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Address</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Salary</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">SG / Step</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Status</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Gov't</th>
                    @elseif ($section === 'trainings')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Title</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">From</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">To</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Hours</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Type</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Sponsor</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Venue</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">URL</th>
                    @elseif ($section === 'voluntary_works')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Organization (name &amp; address)</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">From</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">To</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Hours</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Position / nature</th>
                    @elseif ($section === 'other_infos')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Type</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Title / description</th>
                    @elseif ($section === 'references')
                        <th class="whitespace-nowrap px-3 py-3 text-left">Name</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Address</th>
                        <th class="whitespace-nowrap px-3 py-3 text-left">Contact / email</th>
                    @endif
                    @if ($canManage)
                        <th class="whitespace-nowrap px-3 py-3 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr wire:key="pds-{{ $section }}-{{ $row->id }}">
                        @if ($section === 'dependents')
                            <td class="min-w-[10rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->label ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->extension ?: '—' }}</td>
                            <td class="min-w-[8rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->relationship ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->birthdate ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ ($row->sex_label ?? $row->sex) ?: '—' }}</td>
                            <td class="min-w-[8rem] max-w-[14rem] break-words px-3 py-3 text-slate-600">{{ $row->occupation ?: '—' }}</td>
                            <td class="min-w-[10rem] max-w-[16rem] break-words px-3 py-3 text-slate-600">{{ $row->employer_name ?: '—' }}</td>
                            <td class="min-w-[14rem] max-w-[24rem] break-words px-3 py-3 text-slate-600">{{ $row->employer_address ?: '—' }}</td>
                            <td class="min-w-[7rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->telephone_no ?: '—' }}</td>
                        @elseif ($section === 'educations')
                            <td class="min-w-[7rem] whitespace-nowrap px-3 py-3 font-medium text-slate-800">{{ ($row->education_level_label ?? $row->education_level) ?: '—' }}</td>
                            <td class="min-w-[14rem] max-w-[22rem] break-words px-3 py-3 text-slate-600">{{ $row->school ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[20rem] break-words px-3 py-3 text-slate-600">{{ $row->education_title ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->start_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->end_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->units !== null && $row->units !== '' ? $row->units : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->year_graduated ?: '—' }}</td>
                            <td class="min-w-[10rem] max-w-[18rem] break-words px-3 py-3 text-slate-600">{{ $row->honors ?: '—' }}</td>
                            <td class="min-w-[10rem] max-w-[18rem] break-words px-3 py-3 text-slate-600">{{ $row->url ?: '—' }}</td>
                        @elseif ($section === 'eligibilities')
                            <td class="min-w-[14rem] max-w-[22rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->title ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->rating !== null && $row->rating !== '' ? $row->rating : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->confer_date ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[20rem] break-words px-3 py-3 text-slate-600">{{ $row->confer_place ?: '—' }}</td>
                            <td class="min-w-[8rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->license_no ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->exp_date ?: '—' }}</td>
                        @elseif ($section === 'work_experiences')
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->start_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->end_date ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[18rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->work_position ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[20rem] break-words px-3 py-3 text-slate-600">{{ $row->company_name ?: '—' }}</td>
                            <td class="min-w-[14rem] max-w-[24rem] break-words px-3 py-3 text-slate-600">{{ $row->company_address ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->salary !== null && $row->salary !== '' ? $row->salary : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">
                                @if ($row->salary_grade || ($row->step_inc !== null && $row->step_inc !== ''))
                                    {{ $row->salary_grade ?: '—' }}{{ ($row->step_inc !== null && $row->step_inc !== '') ? '-'.$row->step_inc : '' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="min-w-[7rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->work_status ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ ! empty($row->is_government) ? 'Y' : 'N' }}</td>
                        @elseif ($section === 'trainings')
                            <td class="min-w-[14rem] max-w-[24rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->training_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->start_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->end_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->hours !== null && $row->hours !== '' ? $row->hours : '—' }}</td>
                            <td class="min-w-[7rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->type_name ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[20rem] break-words px-3 py-3 text-slate-600">{{ $row->sponsor ?: '—' }}</td>
                            <td class="min-w-[12rem] max-w-[20rem] break-words px-3 py-3 text-slate-600">{{ $row->training_venue ?: '—' }}</td>
                            <td class="min-w-[10rem] max-w-[18rem] break-words px-3 py-3 text-slate-600">{{ $row->url ?: '—' }}</td>
                        @elseif ($section === 'voluntary_works')
                            <td class="min-w-[14rem] max-w-[24rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->organization_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->start_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->end_date ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->hours !== null && $row->hours !== '' ? $row->hours : '—' }}</td>
                            <td class="min-w-[10rem] max-w-[18rem] break-words px-3 py-3 text-slate-600">{{ $row->position ?: '—' }}</td>
                        @elseif ($section === 'other_infos')
                            <td class="min-w-[8rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ ($row->type_label ?? $row->type) ?: '—' }}</td>
                            <td class="min-w-[14rem] max-w-[28rem] break-words px-3 py-3 font-medium text-slate-800">{{ $row->title ?: '—' }}</td>
                        @elseif ($section === 'references')
                            <td class="min-w-[10rem] whitespace-nowrap px-3 py-3 font-medium text-slate-800">{{ $row->name ?: '—' }}</td>
                            <td class="min-w-[14rem] max-w-[24rem] break-words px-3 py-3 text-slate-600">{{ $row->address ?: '—' }}</td>
                            <td class="min-w-[8rem] whitespace-nowrap px-3 py-3 text-slate-600">{{ $row->telephone_no ?: '—' }}</td>
                        @endif
                        @if ($canManage)
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <button type="button" wire:click="startEdit({{ $row->id }})" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-50">Edit</button>
                                <button type="button" wire:click="deleteRecord({{ $row->id }})" wire:confirm="Delete this record?"
                                        class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    @php
                        $colspan = match ($section) {
                            'dependents' => 9,
                            'educations' => 9,
                            'eligibilities' => 6,
                            'work_experiences' => 9,
                            'trainings' => 8,
                            'voluntary_works' => 5,
                            'other_infos' => 2,
                            'references' => 3,
                            default => 2,
                        };
                        if ($canManage) {
                            $colspan++;
                        }
                    @endphp
                    <tr>
                        <td colspan="{{ $colspan }}" class="px-4 py-8 text-center text-slate-500">No records in this section.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
