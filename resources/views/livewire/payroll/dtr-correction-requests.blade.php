<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">DTR Correction Requests</h2>
            <p class="text-sm text-slate-600">Review correction requests and act on assigned approvals.</p>
        </div>
        <button wire:click="openRequestForm" type="button" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
            New Request
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($showRequestForm)
        <div class="fixed inset-0 z-50" style="height: 100dvh;">
            <div wire:click="closeRequestForm" class="absolute inset-0 bg-slate-950/35"></div>
            <div class="relative z-10 flex min-h-screen w-full items-start justify-center overflow-y-auto px-3 py-6 sm:px-6" style="min-height: 100dvh;">
                <form wire:submit="submit" class="w-full max-w-3xl rounded-md border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h3 class="font-semibold text-slate-900">New DTR Correction</h3>
                        <p class="text-sm text-slate-600">This request will be routed to your configured DTR correction approver.</p>
                        </div>
                        <button wire:click="closeRequestForm" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Close
                        </button>
                    </div>

                    <div class="grid gap-4 p-4 sm:grid-cols-2">
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">DTR Date</span>
                            <input wire:model="dtrDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('dtrDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-slate-500">Correction Type</span>
                        <select wire:model.live="requestType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($requestTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('requestType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    @if ($this->showTimeIn())
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">Time In</span>
                            <input wire:model="requestedTimeIn" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('requestedTimeIn') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif

                    @if ($this->showTimeOut())
                        <div class="space-y-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase text-slate-500">Time Out</span>
                                <input wire:model="requestedTimeOut" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @error('requestedTimeOut') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>

                            <label class="inline-flex min-h-[2rem] items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                <input wire:model="requestedTimeoutNextday" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                                <span>Time out is next day</span>
                            </label>
                        </div>
                    @endif

                    <div class="sm:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <span class="text-xs font-semibold uppercase text-slate-500">Configured Approver</span>
                        <div class="mt-1 font-semibold text-slate-800">
                            {{ $configuredApprover?->full_name ?? 'No approver configured' }}
                        </div>
                        @if (! $configuredApprover)
                            <p class="mt-1 text-xs text-red-600">Set an approver on the DTR Approvers page before submitting a correction request.</p>
                        @endif
                    </div>

                    <label class="sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-slate-500">Reason</span>
                        <textarea wire:model="reason" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Timekeeper was down"></textarea>
                        @error('reason') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="sm:col-span-2">
                        <span class="text-xs font-semibold uppercase text-slate-500">Image Attachment</span>
                        <input wire:model="attachment" type="file" accept="image/*" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <span class="mt-1 block text-xs font-normal text-slate-500">PNG, JPG, GIF, or WebP up to 5 MB.</span>
                        <div wire:loading wire:target="attachment" class="mt-1 text-xs text-slate-500">Uploading image...</div>
                        @error('attachment') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                        <button wire:click="closeRequestForm" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" @disabled(! $configuredApprover) class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6] disabled:cursor-not-allowed disabled:bg-slate-300">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Pending My Approval</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($pendingApprovals as $request)
                    <div class="space-y-3 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $request->employee?->full_name }} &middot; {{ $request->dtr_date->format('M d, Y') }}</p>
                                <p class="text-sm text-slate-600">{{ str_replace('_', ' ', $request->request_type) }} &middot; IN {{ $request->requested_time_in ?: '-' }} &middot; OUT {{ $request->requested_time_out ?: '-' }}</p>
                            </div>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $request->status }}</span>
                        </div>
                        <p class="text-sm text-slate-600">{{ $request->reason }}</p>
                        @if ($request->attachment_url)
                            <a href="{{ $request->attachment_url }}" target="_blank" class="inline-flex items-center text-sm font-semibold text-[#696cff] hover:underline">
                                View attachment
                            </a>
                        @endif
                        <textarea wire:model="approvalRemarks.{{ $request->id }}" rows="2" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Approver remarks"></textarea>
                        <div class="flex justify-end gap-2">
                            <button wire:click="reject({{ $request->id }})" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Reject</button>
                            <button wire:click="approve({{ $request->id }})" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No pending correction requests assigned to you.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">My Requests</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Requested</th>
                            <th class="px-4 py-3 text-left">Attachment</th>
                            <th class="px-4 py-3 text-left">Approver</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($myRequests as $request)
                            <tr>
                                <td class="px-4 py-3">{{ $request->dtr_date->format('M d, Y') }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', $request->request_type) }}</td>
                                <td class="px-4 py-3">IN {{ $request->requested_time_in ?: '-' }} &middot; OUT {{ $request->requested_time_out ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @if ($request->attachment_url)
                                        <a href="{{ $request->attachment_url }}" target="_blank" class="font-semibold text-[#696cff] hover:underline">View</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $request->approver?->full_name ?? $request->approver_emp_id }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $request->status === 'APPROVED' ? 'bg-emerald-50 text-emerald-700' : ($request->status === 'REJECTED' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
                                        {{ $request->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No correction requests yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
