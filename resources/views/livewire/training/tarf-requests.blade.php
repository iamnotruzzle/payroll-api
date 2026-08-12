<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">TARF / LDI Requests</h2>
            <p class="text-sm text-slate-600">File and track learning &amp; development requests on legacy HRIS training tables.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">All apps</a>
            <a href="{{ route('training.calendar') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Calendar</a>
            @if ($canManage)
                <button wire:click="create" type="button" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
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
                <input wire:model.live.debounce.500ms="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search TARF no, title, sponsor, employee">
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
                                <button wire:click="edit('{{ $tarf->tarf_no }}')" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
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

    @if ($drawerOpen)
        <div class="fixed inset-0 z-40 flex justify-end bg-slate-900/40" wire:click="closeDrawer">
            <div class="h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-xl" wire:click.stop>
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">{{ $editingTarfNo ? 'Edit TARF' : 'New TARF / LDI' }}</h3>
                        <p class="text-sm text-slate-500">{{ $editingTarfNo ?: 'Creates a pending PETU request.' }}</p>
                    </div>
                    <button wire:click="closeDrawer" type="button" class="rounded-md border border-slate-300 px-2 py-1 text-sm">Close</button>
                </div>

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
                    @unless ($editingTarfNo)
                        <div class="rounded-md border border-slate-200 p-3">
                            <p class="mb-2 text-sm font-medium text-slate-700">Additional participants</p>
                            <input wire:model.live.debounce.400ms="employeeSearch" type="search" class="mb-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employees">
                            <div class="max-h-40 space-y-1 overflow-y-auto text-sm">
                                @foreach ($employees as $emp)
                                    <label class="flex items-center gap-2 rounded px-1 py-1 hover:bg-slate-50">
                                        <input type="checkbox" @checked(in_array((string) $emp->emp_id, $participantEmpIds, true)) wire:click="toggleParticipant('{{ $emp->emp_id }}')">
                                        <span>{{ $emp->full_name }} <span class="text-slate-400">({{ $emp->emp_id }})</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Supporting documents</span>
                            <input wire:model="supportingFiles" type="file" multiple class="mt-1 block w-full text-sm">
                            <div wire:loading wire:target="supportingFiles" class="text-xs text-slate-500">Uploading…</div>
                        </label>
                    @endunless
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeDrawer" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                        <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
