<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\Employee;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Livewire\Component;

class MySchedule extends Component
{
    public string $empId = '';

    public int $month;

    public int $year;

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.schedule')
            || auth()->user()?->can('self-service.access'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);
        abort_unless($this->empId === (string) (auth()->user()?->emp_id ?? ''), 403);

        $today = CarbonImmutable::today();
        $this->month = (int) $today->month;
        $this->year = (int) $today->year;
    }

    public function render()
    {
        $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $this->empId)
            ->firstOrFail();

        $assignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'unit', 'monthlySchedule'])
            ->where('employee_id', $this->empId)
            ->whereYear('schedule_date', $this->year)
            ->whereMonth('schedule_date', $this->month)
            ->whereHas('monthlySchedule', function ($query) {
                $query->whereIn('status', [
                    MonthlySchedule::STATUS_APPROVED,
                    MonthlySchedule::STATUS_LOCKED,
                ]);
            })
            ->orderBy('schedule_date')
            ->get();

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $daysInMonth = $start->daysInMonth;
        $byDate = $assignments->keyBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString());

        $rows = collect(range(1, $daysInMonth))->map(function (int $day) use ($start, $byDate) {
            $date = $start->setDay($day);
            $key = $date->toDateString();
            /** @var ScheduleAssignment|null $assignment */
            $assignment = $byDate->get($key);

            return [
                'date' => $key,
                'day' => $day,
                'weekday' => $date->format('D'),
                'code' => $assignment?->shiftCode?->code,
                'shift_name' => $assignment?->shiftCode?->name,
                'hours' => $assignment?->shiftCode?->work_hours,
                'unit' => $assignment?->unit?->name,
                'status' => $assignment?->monthlySchedule?->status,
                'is_work' => (bool) $assignment?->shiftCode?->is_work_shift,
                'is_night' => (bool) $assignment?->shiftCode?->is_night_shift,
            ];
        });

        $yearOptions = range((int) CarbonImmutable::today()->year + 1, max(2020, (int) CarbonImmutable::today()->year - 2));

        return view('livewire.self-service.my-schedule', [
            'employee' => $employee,
            'rows' => $rows,
            'assignmentCount' => $assignments->count(),
            'workDays' => $assignments->filter(fn (ScheduleAssignment $a) => (bool) $a->shiftCode?->is_work_shift)->count(),
            'monthOptions' => collect(range(1, 12))
                ->mapWithKeys(fn (int $m) => [$m => CarbonImmutable::createFromDate(2000, $m, 1)->format('F')])
                ->all(),
            'yearOptions' => $yearOptions,
            'periodLabel' => $start->format('F Y'),
        ]);
    }
}
