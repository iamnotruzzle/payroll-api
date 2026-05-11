<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Monthly Draft Schedule</h2>
        <p class="text-sm text-slate-600">Generate, validate, review, approve, and lock monthly schedules.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-5">
            <div>
                <label class="text-sm font-medium">Year</label>
                <input wire:model="year" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Month</label>
                <input wire:model="month" type="number" min="1" max="12" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Department</label>
                <select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Template</label>
                <select wire:model="schedule_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Auto/default</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="generate" class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Generate Draft</button>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[260px_1fr]">
        <aside class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Recent Schedules</h3>
            <div class="mt-3 space-y-2">
                @foreach ($schedules as $item)
                    <button wire:click="$set('selectedScheduleId', {{ $item->id }})" class="w-full rounded-md border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50">
                        <span class="font-medium">{{ $item->year }}-{{ str_pad($item->month, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $item->status }}</span>
                    </button>
                @endforeach
            </div>
        </aside>

        <div class="space-y-4">
            @if ($schedule)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Schedule #{{ $schedule->id }}</h3>
                            <p class="text-sm text-slate-600">{{ $schedule->year }}-{{ str_pad($schedule->month, 2, '0', STR_PAD_LEFT) }} · {{ ucfirst($schedule->status) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button wire:click="validateSchedule" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Validate</button>
                            <button wire:click="review" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @disabled($schedule->isLocked())>Review</button>
                            <button wire:click="approve" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600" @disabled($schedule->isLocked())>Approve</button>
                            <button wire:click="lock" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" @disabled($schedule->status !== 'approved')>Lock</button>
                        </div>
                    </div>
                </div>

                @if ($conflicts)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <h3 class="font-semibold">Conflicts</h3>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($conflicts as $conflict)
                                <li>{{ $conflict['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid grid-cols-7 gap-2 text-sm">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <div class="text-xs font-semibold uppercase text-slate-500">{{ $day }}</div>
                        @endforeach
                        @for ($date = \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->startOfWeek(); $date <= \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->endOfMonth()->endOfWeek(); $date = $date->addDay())
                            @php($key = $date->toDateString())
                            <div class="min-h-28 rounded-md border border-slate-200 p-2 {{ $date->month === $schedule->month ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                                <div class="text-xs font-semibold">{{ $date->day }}</div>
                                <div class="mt-2 space-y-1">
                                    @foreach ($calendar[$key] ?? [] as $assignment)
                                        <div class="truncate rounded px-2 py-1 text-xs {{ $assignment['night'] ? 'bg-indigo-50 text-indigo-800' : 'bg-blue-50 text-blue-800' }}">
                                            {{ $assignment['employee_id'] }} · {{ $assignment['code'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                    Generate or select a schedule to view the calendar.
                </div>
            @endif
        </div>
    </div>
</section>
