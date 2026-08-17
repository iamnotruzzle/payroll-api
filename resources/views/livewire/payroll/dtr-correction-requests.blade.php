@php
    $snapshotRows = function (?array $snapshot): array {
        if (! $snapshot) {
            return [
                'Time In' => '-',
                'AM Out' => '-',
                'PM In' => '-',
                'Time Out' => '-',
                'Next Day Out' => '-',
                'Source' => 'No DTR record',
            ];
        }

        return [
            'Time In' => $snapshot['timein_am'] ?? '-',
            'AM Out' => $snapshot['timeout_am'] ?? '-',
            'PM In' => $snapshot['timein_pm'] ?? '-',
            'Time Out' => $snapshot['timeout_pm'] ?? '-',
            'Next Day Out' => $snapshot['timeout_nextday'] ?? '-',
            'Source' => $snapshot['machine_id'] ?? '-',
        ];
    };

@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">DTR Correction Requests</h2>
            <p class="text-sm text-slate-600">Review correction requests and act on assigned approvals.</p>
        </div>
        <button type="button" x-on:click="erpOverlay.open($wire, 'dtr-correction-new', { dtrDate: @js(now()->toDateString()), requestType: 'TIME_IN', requestedTimeIn: null, requestedTimeOut: null, requestedTimeoutNextday: false, reason: '' })" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
            New Request
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <x-setup-form-modal name="dtr-correction-new" title="New DTR Correction" size="lg">
        <form wire:submit="submit" class="space-y-4">
            <p class="text-sm text-slate-600">This request will be routed to your configured DTR correction approver.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="text-xs font-semibold uppercase text-slate-500">DTR Date</span>
                    <input wire:model="dtrDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('dtrDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="text-xs font-semibold uppercase text-slate-500">Correction Type</span>
                    <select wire:model="requestType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($requestTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('requestType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label x-show="$wire.requestType === 'TIME_IN' || $wire.requestType === 'BOTH'" x-cloak>
                    <span class="text-xs font-semibold uppercase text-slate-500">Time In</span>
                    <input wire:model="requestedTimeIn" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('requestedTimeIn') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="space-y-2" x-show="$wire.requestType === 'TIME_OUT' || $wire.requestType === 'BOTH'" x-cloak>
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

                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 sm:col-span-2">
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

            <div class="flex justify-end gap-2">
                <button x-on:click="erpOverlay.close('dtr-correction-new')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" @disabled(! $configuredApprover) class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6] disabled:cursor-not-allowed disabled:bg-slate-300">
                    Submit Request
                </button>
            </div>
        </form>
    </x-setup-form-modal>

    <x-setup-form-modal name="dtr-correction-edit" title="Update DTR Correction" size="lg">
        <form wire:submit="saveEdit" class="space-y-4">
            <p class="text-sm text-slate-600">Pending requests can be changed until they are approved or rejected.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="text-xs font-semibold uppercase text-slate-500">DTR Date</span>
                    <input wire:model="editDtrDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('editDtrDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="text-xs font-semibold uppercase text-slate-500">Correction Type</span>
                    <select wire:model="editRequestType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($requestTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('editRequestType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label x-show="$wire.editRequestType === 'TIME_IN' || $wire.editRequestType === 'BOTH'" x-cloak>
                    <span class="text-xs font-semibold uppercase text-slate-500">Time In</span>
                    <input wire:model="editRequestedTimeIn" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('editRequestedTimeIn') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="space-y-2" x-show="$wire.editRequestType === 'TIME_OUT' || $wire.editRequestType === 'BOTH'" x-cloak>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Time Out</span>
                        <input wire:model="editRequestedTimeOut" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('editRequestedTimeOut') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="inline-flex min-h-[2rem] items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input wire:model="editRequestedTimeoutNextday" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                        <span>Time out is next day</span>
                    </label>
                </div>

                <label class="sm:col-span-2">
                    <span class="text-xs font-semibold uppercase text-slate-500">Reason</span>
                    <textarea wire:model="editReason" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('editReason') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex justify-end gap-2">
                <button x-on:click="erpOverlay.close('dtr-correction-edit')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                    Save Changes
                </button>
            </div>
        </form>
    </x-setup-form-modal>

    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Pending My Approval</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($pendingApprovals as $request)
                    <div class="space-y-4 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $request->employee?->full_name }} &middot; {{ $request->dtr_date->format('M d, Y') }}</p>
                                <p class="text-sm text-slate-600">{{ str_replace('_', ' ', $request->request_type) }} &middot; requested by {{ $request->requestedBy?->full_name ?? $request->requested_by_emp_id }}</p>
                            </div>
                            <span class="w-fit rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ $request->status }}</span>
                        </div>

                        <details class="rounded-md border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700">
                                DTR preview and remarks
                            </summary>
                            <div class="grid gap-3 border-t border-slate-200 p-3 md:grid-cols-2">
                                @foreach ([
                                    'Original DTR Snapshot' => $request->previous_dtr,
                                    'Requested Correction' => $request->requested_dtr,
                                    'Final Applied DTR' => $request->final_dtr,
                                ] as $title => $snapshot)
                                    <div class="rounded-md border border-slate-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase text-slate-500">{{ $title }}</div>
                                        @if ($title === 'Final Applied DTR' && ! $snapshot)
                                            <p class="mt-2 text-sm text-slate-700">No DTR was applied.</p>
                                        @else
                                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                                                @foreach ($snapshotRows($snapshot) as $label => $value)
                                                    <dt class="text-slate-500">{{ $label }}</dt>
                                                    <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                                                @endforeach
                                            </dl>
                                        @endif
                                        @if ($title === 'Final Applied DTR' && $request->status === 'PENDING')
                                            <p class="mt-2 text-xs text-slate-500">Preview until approved.</p>
                                        @endif
                                    </div>
                                @endforeach

                                <div class="rounded-md border border-slate-200 bg-white p-3">
                                    <div class="text-xs font-semibold uppercase text-slate-500">Approver Remarks</div>
                                    <p class="mt-2 text-sm text-slate-700">{{ $request->approver_remarks ?: 'No remarks yet.' }}</p>
                                </div>
                            </div>
                        </details>

                        <div class="rounded-md border border-slate-200 p-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label>
                                    <span class="text-xs font-semibold uppercase text-slate-500">Correction Type</span>
                                    <select wire:model.live="approvalRequestTypes.{{ $request->id }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        @foreach ($requestTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                @if ($this->approvalShowsTimeIn($request->id))
                                    <label>
                                        <span class="text-xs font-semibold uppercase text-slate-500">Time In</span>
                                        <input wire:model="approvalRequestedTimeIn.{{ $request->id }}" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                @endif

                                @if ($this->approvalShowsTimeOut($request->id))
                                    <div class="space-y-2">
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase text-slate-500">Time Out</span>
                                            <input wire:model="approvalRequestedTimeOut.{{ $request->id }}" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        </label>
                                        <label class="inline-flex min-h-[2rem] items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                            <input wire:model="approvalRequestedTimeoutNextday.{{ $request->id }}" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                                            <span>Next day</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
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
            <div class="divide-y divide-slate-100">
                @forelse ($myRequests as $request)
                    <div class="space-y-4 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $request->dtr_date->format('M d, Y') }} &middot; {{ str_replace('_', ' ', $request->request_type) }}</p>
                                <p class="text-sm text-slate-600">Approver: {{ $request->approver?->full_name ?? $request->approver_emp_id }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($request->status === 'PENDING')
                                    <button type="button" x-on:click="erpOverlay.open($wire, 'dtr-correction-edit', { editingRequestId: {{ $request->id }}, editDtrDate: @js($request->dtr_date->toDateString()), editRequestType: @js($request->request_type), editRequestedTimeIn: @js($request->requested_time_in ? substr((string) $request->requested_time_in, 0, 5) : null), editRequestedTimeOut: @js($request->requested_time_out ? substr((string) $request->requested_time_out, 0, 5) : null), editRequestedTimeoutNextday: @js((bool) $request->requested_timeout_nextday), editReason: @js((string) $request->reason) }, true)" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-slate-50">Update</button>
                                    <button wire:click="cancelRequest({{ $request->id }})" wire:confirm="Cancel this pending DTR correction request?" type="button" class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100">Cancel</button>
                                @endif
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $request->status === 'APPROVED' ? 'bg-emerald-50 text-emerald-700' : ($request->status === 'REJECTED' ? 'bg-red-50 text-red-700' : ($request->status === 'CANCELLED' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700')) }}">
                                    {{ $request->status }}
                                </span>
                            </div>
                        </div>

                        <details class="rounded-md border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700">
                                DTR preview and remarks
                            </summary>
                            <div class="grid gap-3 border-t border-slate-200 p-3 md:grid-cols-2">
                                @foreach ([
                                    'Original DTR Snapshot' => $request->previous_dtr,
                                    'Requested Correction' => $request->requested_dtr,
                                    'Final Applied DTR' => $request->final_dtr,
                                ] as $title => $snapshot)
                                    <div class="rounded-md border border-slate-200 bg-white p-3">
                                        <div class="text-xs font-semibold uppercase text-slate-500">{{ $title }}</div>
                                        @if ($title === 'Final Applied DTR' && ! $snapshot)
                                            <p class="mt-2 text-sm text-slate-700">No DTR was applied.</p>
                                        @else
                                            <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                                                @foreach ($snapshotRows($snapshot) as $label => $value)
                                                    <dt class="text-slate-500">{{ $label }}</dt>
                                                    <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                                                @endforeach
                                            </dl>
                                        @endif
                                        @if ($title === 'Final Applied DTR' && $request->status === 'PENDING')
                                            <p class="mt-2 text-xs text-slate-500">Preview until approved.</p>
                                        @endif
                                    </div>
                                @endforeach

                                <div class="rounded-md border border-slate-200 bg-white p-3">
                                    <div class="text-xs font-semibold uppercase text-slate-500">Approver Remarks</div>
                                    <p class="mt-2 text-sm text-slate-700">{{ $request->approver_remarks ?: 'No remarks yet.' }}</p>
                                </div>
                            </div>
                        </details>

                        <div class="space-y-2 text-sm text-slate-600">
                            <p>{{ $request->reason }}</p>
                            @if ($request->attachment_url)
                                <a href="{{ $request->attachment_url }}" target="_blank" class="inline-flex font-semibold text-[#696cff] hover:underline">View attachment</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No correction requests yet.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
