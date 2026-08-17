<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">TARF / LDI Requests</h2>
            <p class="text-sm text-slate-600">File and track learning and development requests.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">All apps</a>
            <a href="{{ route('training.calendar') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Calendar</a>
            @if ($canManage)
                <button type="button" x-on:click="erpOverlay.open($wire, 'tarf-request', { editingTarfNo: null, trainingName: '', trainingVenue: '', sponsor: '', sponsorType: 1, startDate: @js(now()->toDateString()), endDate: @js(now()->toDateString()), hrs: '8', type: null, mode: 'f2f', description: '', requestorEmpId: @js((string) (auth()->user()?->emp_id ?? '')), participantEmpIds: [], employeeSearch: '' })" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                    New TARF
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search</span>
                <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search TARF no, title, sponsor, employee">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Status
                <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="disapproved">Disapproved</option>
                    <option value="cancelled">Cancelled</option>
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
                    <th class="px-4 py-3 text-left">TARF</th>
                    <th class="px-4 py-3 text-left">Training</th>
                    <th class="px-4 py-3 text-left">Dates</th>
                    <th class="px-4 py-3 text-left">Participants</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tarfs as $tarf)
                    @php
                        $statusKey = \App\Support\Hris\TarfStatuses::keyFor((int) $tarf->status);
                        $statusName = \App\Support\Hris\TarfStatuses::nameFor((int) $tarf->status);
                        $requestor = $tarf->requests->firstWhere('role', 1);
                        $participantEmpIds = $tarf->requests
                            ->where('role', '!=', 1)
                            ->pluck('emp_id')
                            ->map(fn ($id) => (string) $id)
                            ->values()
                            ->all();
                    @endphp
                    <tr wire:key="tarf-{{ $tarf->tarf_no }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $tarf->tarf_no }}</p>
                            <p class="text-xs text-slate-500">{{ $tarf->ldiType?->type ?: 'Type '.$tarf->type }} · {{ strtoupper($tarf->mode) }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <p class="font-medium">{{ $tarf->training_name }}</p>
                            <p class="text-xs text-slate-500">{{ $tarf->sponsor }} · {{ $tarf->training_venue ?: '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ optional($tarf->start_date)->format('Y-m-d') ?: '—' }}
                            <span class="text-slate-400">→</span>
                            {{ optional($tarf->end_date)->format('Y-m-d') ?: '—' }}
                            <p class="text-xs text-slate-500">{{ number_format((float) $tarf->hrs, 1) }} hrs</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $tarf->requests->count() }}</td>
                        <td class="px-4 py-3">
                            @if ($statusKey === 'approved' || $statusKey === 'completed')
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $statusName }}</span>
                            @elseif ($statusKey === 'pending')
                                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $statusName }}</span>
                            @elseif ($statusKey === 'disapproved')
                                <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $statusName }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $statusName }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('training.show', $tarf->tarf_no) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Open</a>
                            <a href="{{ route('training.print', $tarf->tarf_no) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
                            @if ($canManage && (int) $tarf->status === \App\Support\Hris\TarfStatuses::PENDING_PETU)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'tarf-request', { editingTarfNo: @js($tarf->tarf_no), trainingName: @js((string) $tarf->training_name), trainingVenue: @js((string) ($tarf->training_venue ?? '')), sponsor: @js((string) $tarf->sponsor), sponsorType: {{ (int) ($tarf->sponsor_type ?? 1) }}, startDate: @js(optional($tarf->start_date)?->toDateString() ?: ''), endDate: @js(optional($tarf->end_date)?->toDateString() ?: ''), hrs: @js((string) ($tarf->hrs ?? '8')), type: {{ (int) $tarf->type }}, mode: @js((string) ($tarf->mode ?: 'f2f')), description: @js((string) ($tarf->description ?? '')), requestorEmpId: @js((string) ($requestor?->emp_id ?? '')), participantEmpIds: @js($participantEmpIds), employeeSearch: '' }, true)" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                                <button wire:click="cancelRequest('{{ $tarf->tarf_no }}')" wire:confirm="Cancel this TARF?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No TARF / LDI requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-100 px-4 py-3">{{ $tarfs->links() }}</div>
    </section>

    <x-setup-form-drawer name="tarf-request" title="New TARF / LDI" edit-title="Edit TARF" size="lg">
        <form wire:submit="save" class="space-y-3">
            <label class="block text-sm">
                <span class="font-medium text-slate-700">Training name</span>
                <input wire:model="trainingName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('trainingName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Venue</span>
                    <input wire:model="trainingVenue" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="online or venue">
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Mode</span>
                    <select wire:model="mode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="f2f">Face to face</option>
                        <option value="online">Online</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </label>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Sponsor</span>
                    <input wire:model="sponsor" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('sponsor') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Sponsor type</span>
                    <select wire:model="sponsorType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="1">External</option>
                        <option value="2">Internal</option>
                    </select>
                </label>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Start</span>
                    <input wire:model="startDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">End</span>
                    <input wire:model="endDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-slate-700">Hours</span>
                    <input wire:model="hrs" type="number" step="0.5" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </label>
            </div>
            <label class="block text-sm">
                <span class="font-medium text-slate-700">LDI type</span>
                <select wire:model="type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select type</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->type }}</option>
                    @endforeach
                </select>
                @error('type') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="font-medium text-slate-700">Requestor</span>
                <select wire:model="requestorEmpId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select employee</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->emp_id }}">{{ $emp->full_name }} ({{ $emp->emp_id }})</option>
                    @endforeach
                </select>
                @error('requestorEmpId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
            <div class="rounded-md border border-slate-200 p-3" x-show="!editing" x-cloak>
                <p class="mb-2 text-sm font-medium text-slate-700">Additional participants</p>
                <input wire:model.lazy="employeeSearch" type="search" class="mb-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employees">
                <div class="max-h-40 space-y-1 overflow-y-auto text-sm">
                    @foreach ($employees as $emp)
                        <label class="flex items-center gap-2 rounded px-1 py-1 hover:bg-slate-50">
                            <input type="checkbox" @checked(in_array((string) $emp->emp_id, $participantEmpIds, true)) wire:click="toggleParticipant('{{ $emp->emp_id }}')">
                            <span>{{ $emp->full_name }} <span class="text-slate-400">({{ $emp->emp_id }})</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <label class="block text-sm" x-show="!editing" x-cloak>
                <span class="font-medium text-slate-700">Supporting documents</span>
                <input wire:model="supportingFiles" type="file" multiple class="mt-1 block w-full text-sm">
                <div wire:loading wire:target="supportingFiles" class="text-xs text-slate-500">Uploading…</div>
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" x-on:click="erpOverlay.close('tarf-request')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
