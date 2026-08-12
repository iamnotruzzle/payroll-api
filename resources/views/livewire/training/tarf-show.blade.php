<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">{{ $tarf->tarf_no }}</h2>
            <p class="text-sm text-slate-600">{{ $tarf->training_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['training.view', 'training.manage', 'training.approve'])
                <a href="{{ route('training.requests') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Back</a>
            @else
                <a href="{{ route('self-service.training') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">My Training</a>
            @endcanany
            @if ($canReschedule)
                <button type="button" wire:click="openReschedule" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Reschedule</button>
            @endif
            <a href="{{ route('training.print', $tarf->tarf_no) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($showReschedule)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold uppercase text-slate-500">Reschedule</h3>
            <form wire:submit="saveReschedule" class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">Start<input wire:model="rescheduleStart" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-sm">End<input wire:model="rescheduleEnd" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-sm">Hours<input wire:model="rescheduleHrs" type="number" step="0.5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="block text-sm sm:col-span-2">Notes<textarea wire:model="rescheduleNotes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea></label>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save dates</button>
                    <button type="button" wire:click="cancelReschedule" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div><dt class="text-xs uppercase text-slate-500">Status</dt><dd class="font-semibold">{{ $statusName }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Type / mode</dt><dd>{{ $tarf->ldiType?->type ?: '—' }} · {{ strtoupper($tarf->mode) }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Dates</dt><dd>{{ optional($tarf->start_date)->format('Y-m-d') }} → {{ optional($tarf->end_date)->format('Y-m-d') }} ({{ number_format((float) $tarf->hrs, 1) }} hrs)</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Venue</dt><dd>{{ $tarf->training_venue ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs uppercase text-slate-500">Sponsor</dt><dd>{{ $tarf->sponsor }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">PETU</dt><dd>{{ $tarf->approvedByPetu?->full_name ?: '—' }} @if($tarf->petu_notes)<span class="block text-xs text-slate-500">{{ $tarf->petu_notes }}</span>@endif</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">MCC</dt><dd>{{ $tarf->approvedByMcc?->full_name ?: '—' }} @if($tarf->mcc_notes)<span class="block text-xs text-slate-500">{{ $tarf->mcc_notes }}</span>@endif</dd></div>
            </dl>
        </div>

        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold uppercase text-slate-500">Participants</h3>
            <ul class="space-y-2 text-sm">
                @foreach ($tarf->requests as $req)
                    <li>
                        <span class="font-medium">{{ $req->employee?->full_name ?: $req->emp_id }}</span>
                        <span class="text-xs text-slate-500">
                            ({{ $req->emp_id }}) · {{ (int) $req->role === 1 ? 'Requestor' : 'Participant' }}
                            · {{ (int) $req->accepted === 1 ? 'Accepted' : 'Pending invite' }}
                            @if ((int) $req->ob_ot > 0)
                                · OB/OT {{ (int) $req->ob_ot }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase text-slate-500">Files</h3>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2 text-left">File</th>
                    <th class="px-3 py-2 text-left">Type</th>
                    <th class="px-3 py-2 text-left">By</th>
                    <th class="px-3 py-2 text-right">Download</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tarf->uploadedFiles as $file)
                    <tr>
                        <td class="px-3 py-2">{{ $file->filename }}@if($file->remarks)<span class="block text-xs text-slate-500">{{ $file->remarks }}</span>@endif</td>
                        <td class="px-3 py-2">{{ (int) $file->type === \App\Models\Hris\UploadedFile::TYPE_SUPPORTING ? 'Supporting' : 'Report' }}</td>
                        <td class="px-3 py-2">{{ $file->uploader?->full_name ?: $file->uploaded_by }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('training.download', [$tarf->tarf_no, $file->id]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50">Download</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-center text-slate-500">No uploaded files.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($canUpload)
            <form wire:submit="uploadReports" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                <p class="text-sm font-medium text-slate-700">Upload training report</p>
                <input wire:model="reportFiles" type="file" multiple class="block w-full text-sm">
                <input wire:model="uploadRemarks" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Remarks (optional)">
                @error('reportFiles') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Upload</button>
            </form>
        @endif
    </section>
</div>
