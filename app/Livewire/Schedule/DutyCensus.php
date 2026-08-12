<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ScheduleScopeService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class DutyCensus extends Component
{
    public int $year;

    public int $month;

    public ?int $unit_id = null;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.view'), 403);
        abort_unless($scopeService->profileForDepartment($this->departmentId())->uses_census, 404);

        $today = CarbonImmutable::today();
        $this->year = (int) $today->year;
        $this->month = (int) $today->month;
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = $this->departmentId();
        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $days = collect(range(1, $start->daysInMonth))
            ->map(fn (int $day) => $start->setDay($day));

        $schedule = MonthlySchedule::query()
            ->where('department_id', $departmentId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->latest('id')
            ->first();

        $shiftCodes = ShiftCode::query()
            ->where('is_active', true)
            ->where('is_work_shift', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->orderBy('code')
            ->get();

        $counts = [];
        foreach ($days as $day) {
            foreach ($shiftCodes as $shift) {
                $counts[$day->toDateString()][$shift->code] = 0;
            }
        }

        if ($schedule) {
            ScheduleAssignment::query()
                ->with('shiftCode')
                ->where('monthly_schedule_id', $schedule->id)
                ->when($this->unit_id, fn ($q) => $q->where('unit_id', $this->unit_id))
                ->whereHas('shiftCode', fn ($q) => $q->where('is_work_shift', true))
                ->get()
                ->each(function (ScheduleAssignment $assignment) use (&$counts) {
                    $date = $assignment->schedule_date->toDateString();
                    $code = $assignment->shiftCode?->code;
                    if ($code === null || ! isset($counts[$date])) {
                        return;
                    }
                    if (! isset($counts[$date][$code])) {
                        $counts[$date][$code] = 0;
                    }
                    $counts[$date][$code]++;
                });
        }

        return view('livewire.schedule.duty-census', [
            'department' => auth()->user()?->employee?->department,
            'schedule' => $schedule,
            'days' => $days,
            'shiftCodes' => $shiftCodes,
            'counts' => $counts,
            'unitOptions' => $scopeService->unitsForDepartment($departmentId),
            'monthOptions' => collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => CarbonImmutable::createFromDate(2000, $m, 1)->format('F')])
                ->all(),
            'yearOptions' => range((int) CarbonImmutable::today()->year + 1, max(2020, (int) CarbonImmutable::today()->year - 2)),
        ]);
    }

    private function departmentId(): ?int
    {
        $id = auth()->user()?->employee?->department_id;

        return $id !== null ? (int) $id : null;
    }
}
