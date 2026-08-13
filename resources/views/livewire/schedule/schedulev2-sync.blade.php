<section class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Import from NDOS</h2>
            <p class="text-sm text-slate-600">
                Import <strong>approved</strong> (status <code class="text-xs">A</code>) NDOS (Nursing Division Online Scheduling) rows into this module.
                Every import re-compares mapped assignments and updates when shift/unit/emp/date changed.
                Imported months stay <strong>locked</strong>. Import never auto-triggers lock→DTR, and never deletes local locked history if source later un-approves a row.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-md px-3 py-1 text-xs font-semibold {{ $isCno ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $modeLabel }}
            </span>
            <a
                href="{{ route('schedule.dashboard') }}"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Back to Schedules
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errorMessage)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">NDOS connection / sync issue</p>
            <p class="mt-1">{{ $errorMessage }}</p>
        </div>
    @endif

    @unless ($canManage)
        <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            You can view this page, but dry-run and apply require the <strong>schedule.manage</strong> permission.
        </div>
    @endunless

    <form wire:submit.prevent class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium">Date range mode</label>
                <select wire:model.live="rangeMode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    <option value="months">Months back / ahead (defaults)</option>
                    <option value="dates">Exact from / to dates</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Department (optional)</label>
                <select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->department_id }}">{{ $dept->department }} (#{{ $dept->department_id }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Defaults to your current department. Matches HRIS home or duty-location-resolved dept (floaters included).</p>
            </div>
        </div>

        @if ($rangeMode === 'months')
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">Months back</label>
                    <input wire:model.live="months_back" type="number" min="0" max="36" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    @error('months_back') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Months ahead</label>
                    <input wire:model.live="months_ahead" type="number" min="0" max="36" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    @error('months_ahead') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-500">Resolved range: <strong>{{ $from }}</strong> → <strong>{{ $to }}</strong></p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium">From</label>
                    <input wire:model="from" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    @error('from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">To</label>
                    <input wire:model="to" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $canManage)>
                    @error('to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3 {{ $isCno ? 'bg-cyan-50 border-cyan-200' : '' }}">
            <input wire:model="filter_division" type="checkbox" class="mt-1" @disabled(! $canManage)>
            <span>
                <span class="block text-sm font-semibold text-slate-900">
                    Limit to CNO / Nursing division (division_id = {{ $cnoDivisionId }})
                </span>
                <span class="mt-1 block text-sm text-slate-600">
                    Keeps rows when HRIS home dept, duty-location-resolved dept, or NDOS location.division_id is in that division.
                    Default on for CNO users.
                </span>
            </span>
        </label>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="dryRun"
                wire:loading.attr="disabled"
                wire:target="dryRun,apply"
                @disabled(! $canManage)
                class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading wire:target="dryRun" class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700"></span>
                <span wire:loading.remove wire:target="dryRun">Dry-run preview</span>
                <span wire:loading wire:target="dryRun">Previewing…</span>
            </button>
            <button
                type="button"
                wire:click="apply"
                wire:confirm="Import from NDOS? Writes approved (A) schedules under locked months. Re-compares existing mapped rows. Does not delete local history or sync DTR."
                wire:loading.attr="disabled"
                wire:target="dryRun,apply"
                @disabled(! $canManage)
                class="inline-flex items-center justify-center gap-2 rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading wire:target="apply" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                <span wire:loading.remove wire:target="apply">Import / Apply</span>
                <span wire:loading wire:target="apply">Importing…</span>
            </button>
        </div>
        <p class="text-xs text-slate-500">Run a preview first, then review the results before applying the import.</p>
    </form>

    <div class="rounded-lg border border-red-200 bg-red-50 p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-red-900">Full backfill (destructive)</h3>
        <p class="mt-2 text-sm text-red-900">
            Replaces current scheduling configuration and optionally assignments with the selected source data.
            Employee and payroll records are not removed. Review the preview carefully before continuing.
        </p>
        <p class="mt-2 text-xs text-red-800">
            CLI preferred:
            <code class="rounded bg-red-100 px-1">php artisan schedule:backfill-schedulev2 --dry-run</code>
            then
            <code class="rounded bg-red-100 px-1">--apply --force</code>
            (optional <code class="rounded bg-red-100 px-1">--with-assignments --division={{ $cnoDivisionId }}</code>).
        </p>

        <div class="mt-4 space-y-3">
            <label class="flex items-start gap-3 rounded-md border border-red-200 bg-white px-4 py-3">
                <input wire:model="backfillWithAssignments" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Also import approved assignments after references</span>
                    <span class="mt-1 block text-sm text-slate-600">
                        Uses the date range / CNO division filter above. Same as assignment sync (status A, locked months, no DTR).
                    </span>
                </span>
            </label>

            <div>
                <label class="text-sm font-medium text-red-950">Type <code class="rounded bg-red-100 px-1">BACKFILL</code> to enable Apply</label>
                <input
                    wire:model="backfillConfirm"
                    type="text"
                    autocomplete="off"
                    class="mt-1 w-full max-w-md rounded-md border border-red-300 px-3 py-2 text-sm"
                    @disabled(! $canManage)
                    placeholder="BACKFILL"
                >
                @error('backfillConfirm') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="dryRunBackfill"
                    wire:loading.attr="disabled"
                    wire:target="dryRunBackfill,applyBackfill"
                    @disabled(! $canManage)
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-900 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="dryRunBackfill">Dry-run full backfill</span>
                    <span wire:loading wire:target="dryRunBackfill">Previewing…</span>
                </button>
                <button
                    type="button"
                    wire:click="applyBackfill"
                    wire:confirm="This will TRUNCATE schedule tables then rewrite references from NDOS. Lock→DTR will NOT run. Continue?"
                    wire:loading.attr="disabled"
                    wire:target="dryRunBackfill,applyBackfill"
                    @disabled(! $canManage)
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="applyBackfill">Apply full backfill</span>
                    <span wire:loading wire:target="applyBackfill">Backfilling…</span>
                </button>
            </div>
        </div>

        @if ($backfillResult)
            <div class="mt-4 rounded-md border border-red-200 bg-white p-4 text-sm text-slate-800">
                <p class="font-semibold">
                    Backfill summary
                    <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs uppercase tracking-wide text-slate-600">
                        {{ !empty($backfillResult['dry_run']) ? 'dry-run' : 'apply' }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-slate-500">Batch: {{ $backfillResult['batch_key'] ?? '—' }}</p>

                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tables cleared / would clear</p>
                        <ul class="mt-1 max-h-40 list-disc space-y-0.5 overflow-y-auto pl-5 text-xs">
                            @foreach (($backfillResult['cleared'] ?? []) as $table => $count)
                                <li><code>{{ $table }}</code> — {{ $count }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created / estimated</p>
                        <ul class="mt-1 max-h-40 list-disc space-y-0.5 overflow-y-auto pl-5 text-xs">
                            @foreach (($backfillResult['created'] ?? []) as $entity => $count)
                                <li>{{ $entity }} — {{ $count }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if (!empty($backfillResult['assignment_sync']))
                    <p class="mt-3 text-xs text-slate-600">
                        Assignment sync:
                        source {{ $backfillResult['assignment_sync']['source_count'] ?? 0 }},
                        create {{ $backfillResult['assignment_sync']['created'] ?? 0 }},
                        update {{ $backfillResult['assignment_sync']['updated'] ?? 0 }},
                        unchanged {{ $backfillResult['assignment_sync']['unchanged'] ?? 0 }},
                        skip {{ $backfillResult['assignment_sync']['skipped'] ?? 0 }}.
                    </p>
                @endif

                @if (!empty($backfillResult['errors']))
                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                        <p class="font-semibold">Errors (first 10)</p>
                        <ul class="mt-1 list-disc pl-4">
                            @foreach (array_slice($backfillResult['errors'], 0, 10) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($result)
        @php
            $skipReasons = $result['skip_reasons'] ?? [];
            $sourceCount = (int) ($result['source_count'] ?? 0);
            $created = (int) ($result['created'] ?? 0);
            $updated = (int) ($result['updated'] ?? 0);
            $unchanged = (int) ($result['unchanged'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $accounted = (int) ($result['accounted'] ?? ($created + $updated + $unchanged + $skipped));
        @endphp

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">
                    Summary
                    <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-slate-600">
                        {{ !empty($result['dry_run']) ? 'dry-run' : 'apply' }}
                    </span>
                </h3>
                <p class="text-xs text-slate-500">Batch: {{ $result['batch_key'] ?? '—' }}</p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Source (A)</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $sourceCount }}</p>
                </div>
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Create</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $created }}</p>
                </div>
                <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Update</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-900">{{ $updated }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-600">Unchanged</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $unchanged }}</p>
                </div>
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Skip</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $skipped }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Metric</th>
                            <th class="px-3 py-2">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-3 py-2">Range</td>
                            <td class="px-3 py-2">{{ $result['from'] ?? '—' }} → {{ $result['to'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">Connection OK</td>
                            <td class="px-3 py-2">{{ !empty($result['connection_ok']) ? 'yes' : 'no' }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">Months touched</td>
                            <td class="px-3 py-2">{{ $result['months_touched'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">Locked months (no DTR)</td>
                            <td class="px-3 py-2">{{ $result['locked_months'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">Accounted (create+update+unchanged+skip)</td>
                            <td class="px-3 py-2">
                                {{ $accounted }}
                                @if ($sourceCount === $accounted)
                                    <span class="text-emerald-700">✓</span>
                                @else
                                    <span class="text-red-700">✗ mismatch</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">skipped_oc_or_empty_label</td>
                            <td class="px-3 py-2">{{ $skipReasons['skipped_oc_or_empty_label'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">skipped_no_employee</td>
                            <td class="px-3 py-2">{{ $skipReasons['skipped_no_employee'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">skipped_department_filter</td>
                            <td class="px-3 py-2">{{ $skipReasons['skipped_department_filter'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">skipped_division_filter</td>
                            <td class="px-3 py-2">{{ $skipReasons['skipped_division_filter'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">skipped_no_shift_code</td>
                            <td class="px-3 py-2">{{ $skipReasons['skipped_no_shift_code'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">Errors</td>
                            <td class="px-3 py-2">{{ count($result['errors'] ?? []) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if (!empty($result['errors']))
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Errors (first 20)</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach (array_slice($result['errors'], 0, 20) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</section>
