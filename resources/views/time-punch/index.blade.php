<x-layouts.app title="Time Punch">
    @php
        $employee = auth()->user()?->employee;
        $displayDtr = $openDtr ?? $todayDtr;
        $timeOutValue = $displayDtr?->timeout_nextday
            ? \Carbon\CarbonImmutable::parse($displayDtr->timeout_nextday)->format('M d, h:i A')
            : ($displayDtr?->timeout_pm ? \Carbon\CarbonImmutable::parse($displayDtr->timeout_pm)->format('h:i A') : null);
        $canTimeIn = ! $todayDtr?->timein_am && (! $openDtr || $openDtr->dtr_date?->toDateString() === $today);
        $canTimeOut = $openDtr?->timein_am && ! $openDtr?->timeout_pm && ! $openDtr?->timeout_nextday;
        $activeDtrDate = $displayDtr?->dtr_date?->toDateString() ?? $today;
    @endphp

    <section class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Time Punch</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $employee?->full_name ?? auth()->user()?->emp_id }} · {{ \Carbon\CarbonImmutable::today()->format('F d, Y') }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <span class="font-semibold">Machine ID:</span> 103
                </div>
            </div>

            @if (session('status'))
                <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-md border border-slate-200 bg-white p-3">
                    <p class="text-xs font-semibold uppercase text-slate-500">Time In</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $displayDtr?->timein_am ? \Carbon\CarbonImmutable::parse($displayDtr->timein_am)->format('h:i A') : '-' }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-3">
                    <p class="text-xs font-semibold uppercase text-slate-500">Time Out</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $timeOutValue ?? '-' }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-3">
                    <p class="text-xs font-semibold uppercase text-slate-500">DTR Date</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ \Carbon\CarbonImmutable::parse($activeDtrDate)->format('M d') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('time-punch.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <button
                    type="submit"
                    name="punch"
                    value="time_in"
                    @disabled(! $canTimeIn)
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <path d="M10 17l5-5-5-5"></path>
                        <path d="M15 12H3"></path>
                    </svg>
                    Time In
                </button>

                <button
                    type="submit"
                    name="punch"
                    value="time_out"
                    @disabled(! $canTimeOut)
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <path d="M16 17l5-5-5-5"></path>
                        <path d="M21 12H9"></path>
                    </svg>
                    Time Out
                </button>
            </form>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold text-slate-900">Recent DTR Records</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Time In</th>
                            <th class="px-4 py-3">AM Out</th>
                            <th class="px-4 py-3">PM In</th>
                            <th class="px-4 py-3">Time Out</th>
                            <th class="px-4 py-3">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentDtrs as $dtr)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $dtr->dtr_date?->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr->timein_am ? \Carbon\CarbonImmutable::parse($dtr->timein_am)->format('h:i A') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr->timeout_am ? \Carbon\CarbonImmutable::parse($dtr->timeout_am)->format('h:i A') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr->timein_pm ? \Carbon\CarbonImmutable::parse($dtr->timein_pm)->format('h:i A') : '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($dtr->timeout_nextday)
                                        {{ \Carbon\CarbonImmutable::parse($dtr->timeout_nextday)->format('M d, h:i A') }}
                                    @elseif ($dtr->timeout_pm)
                                        {{ \Carbon\CarbonImmutable::parse($dtr->timeout_pm)->format('h:i A') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $dtr->machine_id ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No DTR records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
