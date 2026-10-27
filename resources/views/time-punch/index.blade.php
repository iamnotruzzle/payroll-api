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

    <section class="time-punch space-y-5" aria-label="Time punch and attendance history">

        <header class="punch-heading">
            <div>
                <h2>Time Punch</h2>
                <p>{{ $employee?->full_name ?? auth()->user()?->emp_id }}</p>
            </div>
            <time datetime="{{ $today }}">{{ \Carbon\CarbonImmutable::parse($today)->format('l, F j, Y') }}</time>
        </header>

        <div class="punch-panel">
            <div class="punch-state">
                <span class="punch-state-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div>
                    <h3>{{ $canTimeOut ? 'You are clocked in' : ($canTimeIn ? 'Ready to start your day?' : 'Your punches are complete') }}</h3>
                    <p id="punch-guidance">{{ $canTimeOut ? 'Record your time out when you finish your shift.' : ($canTimeIn ? 'Record your time in to begin your attendance record.' : 'Your time in and time out have been recorded for today.') }}</p>
                </div>
            </div>
            @if ($canTimeOut && $activeDtrDate !== $today)
                <div class="punch-notice" role="note">Your open record is from {{ \Carbon\CarbonImmutable::parse($activeDtrDate)->format('M j, Y') }}. Record your pending time out before starting a new day.</div>
            @endif

            @if (session('status'))
                <div role="status" class="punch-notice">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('warning'))
                <div role="alert" class="punch-notice">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="punch-notice" role="alert">{{ $errors->first() }}</div>
            @endif

            <dl class="punch-summary">
                <div>
                    <dt>Time In</dt>
                    <dd>{{ $displayDtr?->timein_am ? \Carbon\CarbonImmutable::parse($displayDtr->timein_am)->format('h:i A') : '-' }}
                    <span>{{ $displayDtr?->timein_am ? 'Recorded' : 'Not recorded' }}</span></dd>
                </div>
                <div>
                    <dt>Time Out</dt>
                    <dd>{{ $timeOutValue ?? '-' }}
                    <span>{{ $timeOutValue ? 'Recorded' : 'Not recorded' }}</span></dd>
                </div>
            </dl>
            <div class="punch-meta">
                <span>DTR date <strong>{{ \Carbon\CarbonImmutable::parse($activeDtrDate)->format('M j, Y') }}</strong></span>
                <span>Machine ID <strong>{{ \App\Services\Attendance\TimePunchService::MACHINE_ID }}</strong></span>
            </div>

            <form method="POST" action="{{ route('time-punch.store') }}" class="punch-actions" aria-describedby="punch-guidance" x-data="{ submitting: false, action: '' }" x-on:submit="if (submitting) { $event.preventDefault(); return; } action = $event.submitter?.value; submitting = true" :aria-busy="submitting">
                @csrf
                <button
                    type="submit"
                    name="punch"
                    value="time_in"
                    @disabled(! $canTimeIn)
                    :aria-disabled="submitting || @js(! $canTimeIn)"
                    class="punch-button punch-button-primary"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <path d="M10 17l5-5-5-5"></path>
                        <path d="M15 12H3"></path>
                    </svg>
                    <span x-text="submitting &amp;&amp; action === 'time_in' ? 'Recording...' : 'Time In'">Time In</span>
                </button>

                <button
                    type="submit"
                    name="punch"
                    value="time_out"
                    @disabled(! $canTimeOut)
                    :aria-disabled="submitting || @js(! $canTimeOut)"
                    class="punch-button punch-button-out"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <path d="M16 17l5-5-5-5"></path>
                        <path d="M21 12H9"></path>
                    </svg>
                    <span x-text="submitting &amp;&amp; action === 'time_out' ? 'Recording...' : 'Time Out'">Time Out</span>
                </button>
            </form>
        </div>

        <div class="punch-history">
            <div class="punch-history-heading">
                <h3>Recent DTR Records</h3>
                <p>Your latest {{ $recentDtrs->count() }} {{ $recentDtrs->count() === 1 ? 'record' : 'records' }}</p>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <caption class="sr-only">Recent attendance records, including morning and afternoon punches and machine source</caption>
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th scope="col" class="px-4 py-3">Date</th>
                            <th scope="col" class="px-4 py-3">Time In</th>
                            <th scope="col" class="px-4 py-3">AM Out</th>
                            <th scope="col" class="px-4 py-3">PM In</th>
                            <th scope="col" class="px-4 py-3">Time Out</th>
                            <th scope="col" class="px-4 py-3">Source</th>
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
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No DTR records yet. Your attendance will appear here after your first time in.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.app>
