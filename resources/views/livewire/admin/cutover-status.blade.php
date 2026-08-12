<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">HRIS Cutover Status</h2>
            <p class="text-sm text-slate-600">
                Read-only env / flag snapshot for Phase 9 dual-run and freeze. Ops flips flags in
                <code class="rounded bg-slate-100 px-1">.env</code> — this page does not change them.
            </p>
        </div>
        <p class="text-xs font-medium text-slate-500">
            Runbook: <code class="rounded bg-slate-100 px-1.5 py-0.5">docs/hris-cutover.md</code>
        </p>
    </div>

    @php
        $boolBadge = function (bool $on, string $onLabel = 'On', string $offLabel = 'Off'): array {
            return $on
                ? ['class' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'label' => $onLabel]
                : ['class' => 'bg-slate-50 text-slate-600 border-slate-200', 'label' => $offLabel];
        };
    @endphp

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['title' => 'HRIS_USE_V2', 'on' => $snapshot['use_v2'], 'hint' => 'Employees UI on hris_v2'],
            ['title' => 'HRIS_FREEZE_LEGACY_WRITES', 'on' => $snapshot['freeze_legacy_writes'], 'hint' => 'Block legacy employee-master writes'],
            ['title' => 'API_REQUIRE_AUTH', 'on' => $snapshot['api_require_auth'], 'hint' => '/api/* lockdown'],
            ['title' => 'NDOS DB', 'on' => $snapshot['schedulev2']['configured'], 'hint' => $snapshot['schedulev2']['database'] ?: 'Not configured'],
        ] as $card)
            @php($badge = $boolBadge($card['on']))
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $card['title'] }}</p>
                <p class="mt-2">
                    <span class="inline-flex rounded-md border px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                </p>
                <p class="mt-2 text-xs text-slate-500">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Module cutover flags</h3>
        <p class="mt-1 text-xs text-slate-500">When On, this app is canonical for that module (banner + ops signal). Defaults Off until dual-run signoff.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Module</th>
                        <th class="px-3 py-2 text-left">Env</th>
                        <th class="px-3 py-2 text-left">State</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($snapshot['module_flags'] as $flag)
                        @php($badge = $boolBadge($flag['enabled'], 'Canonical here', 'Dual-run / legacy OK'))
                        <tr>
                            <td class="px-3 py-2.5 font-medium text-slate-800">{{ $flag['label'] }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-600">{{ $flag['env'] }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-3 lg:grid-cols-2">
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">NDOS connection</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Connection</dt>
                    <dd class="font-mono text-xs text-slate-800">{{ $snapshot['schedulev2']['connection'] }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Database</dt>
                    <dd class="font-mono text-xs text-slate-800">{{ $snapshot['schedulev2']['database'] ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Reachable</dt>
                    <dd>
                        @if ($snapshot['schedulev2']['reachable'] === true)
                            <span class="font-semibold text-emerald-700">Yes</span>
                        @elseif ($snapshot['schedulev2']['reachable'] === false)
                            <span class="font-semibold text-rose-700">No</span>
                        @else
                            <span class="text-slate-500">n/a</span>
                        @endif
                    </dd>
                </div>
            </dl>
            @if ($snapshot['schedulev2']['error'])
                <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ \Illuminate\Support\Str::limit($snapshot['schedulev2']['error'], 240) }}
                </p>
            @endif
            <p class="mt-3 text-xs text-slate-500">
                Sync UI: <a href="{{ route('schedule.schedulev2-sync') }}" class="font-semibold text-[#696cff] hover:underline">Schedule → Import from NDOS</a>
                (requires <code class="rounded bg-slate-100 px-1">schedule.view</code>).
            </p>
        </div>

        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Last NDOS sync run</h3>
            @if ($snapshot['last_sync_run'])
                @php($run = $snapshot['last_sync_run'])
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Run #{{ $run['id'] }}</dt>
                        <dd class="font-semibold text-slate-800">{{ $run['status'] }}{{ $run['dry_run'] ? ' (dry-run)' : '' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Range</dt>
                        <dd class="text-xs text-slate-700">{{ $run['from_date'] }} → {{ $run['to_date'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Division / Dept</dt>
                        <dd class="text-xs text-slate-700">
                            {{ $run['division_id'] ? 'div '.$run['division_id'] : '—' }}
                            /
                            {{ $run['department_id'] ? 'dept '.$run['department_id'] : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Finished</dt>
                        <dd class="text-xs text-slate-700">{{ $run['finished_at'] ?: $run['started_at'] ?: '—' }}</dd>
                    </div>
                    @if (is_array($run['stats']) && $run['stats'] !== [])
                        <div>
                            <dt class="text-slate-500">Stats</dt>
                            <dd class="mt-1 rounded-md bg-slate-50 p-2 font-mono text-[11px] text-slate-700">{{ json_encode($run['stats']) }}</dd>
                        </div>
                    @endif
                    @if ($run['error_count'] > 0)
                        <p class="text-xs font-semibold text-amber-800">{{ $run['error_count'] }} error(s) recorded on run.</p>
                    @endif
                </dl>
            @else
                <p class="mt-3 text-sm text-slate-500">No sync runs recorded yet (or sync tables not migrated).</p>
            @endif
        </div>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Go-live checklist (hints)</h3>
        <ul class="mt-3 space-y-2">
            @foreach ($snapshot['checklist'] as $item)
                <li class="flex gap-3 rounded-md border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-sm">
                    <span class="mt-0.5 shrink-0 text-base" aria-hidden="true">{{ $item['done'] ? '✓' : '○' }}</span>
                    <div>
                        <p class="font-semibold text-slate-800">{{ $item['label'] }}</p>
                        <p class="text-xs text-slate-500">{{ $item['note'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-slate-500">
            Full ordered steps and signoff live in <code class="rounded bg-slate-100 px-1">docs/hris-cutover.md</code>.
            Do not mark live pilots complete without dual-run evidence.
        </p>
    </section>
</div>
