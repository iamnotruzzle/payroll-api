<?php

namespace App\Livewire\Schedule;

use App\Models\Department;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleTemplate;
use App\Services\Schedule\ScheduleApprovalService;
use App\Services\Schedule\ScheduleConflictValidator;
use App\Services\Schedule\ScheduleDraftGenerationService;
use App\Services\Schedule\ScheduleLockService;
use Livewire\Component;

class ScheduleDashboard extends Component
{
    public int $year;
    public int $month;
    public ?int $department_id = null;
    public ?int $schedule_template_id = null;
    public ?int $selectedScheduleId = null;
    public array $conflicts = [];

    public function mount(): void
    {
        $nextMonth = now()->addMonth();
        $this->year = (int) $nextMonth->format('Y');
        $this->month = (int) $nextMonth->format('n');
    }

    public function render()
    {
        $schedule = $this->selectedScheduleId
            ? MonthlySchedule::with('assignments.shiftCode')->find($this->selectedScheduleId)
            : MonthlySchedule::with('assignments.shiftCode')
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
                ->latest('id')
                ->first();

        return view('livewire.schedule.schedule-dashboard', [
            'departments' => Department::orderBy('department')->get(['department_id', 'department']),
            'templates' => ScheduleTemplate::where('is_active', true)->orderBy('name')->get(),
            'schedules' => MonthlySchedule::orderByDesc('year')->orderByDesc('month')->limit(12)->get(),
            'schedule' => $schedule,
            'calendar' => $this->calendar($schedule),
        ]);
    }

    public function generate(ScheduleDraftGenerationService $service): void
    {
        $data = $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'department_id' => ['nullable', 'integer'],
            'schedule_template_id' => ['nullable', 'integer'],
        ]);

        $result = $service->generate($data['year'], $data['month'], $data['department_id'], $data['schedule_template_id'], 'web');
        $this->selectedScheduleId = $result['schedule']->id;
        $this->conflicts = $result['conflicts'];
        session()->flash('status', 'Draft schedule generated.');
    }

    public function validateSchedule(ScheduleConflictValidator $validator): void
    {
        $schedule = MonthlySchedule::findOrFail($this->selectedScheduleId);
        $this->conflicts = $validator->validate($schedule);
    }

    public function review(ScheduleApprovalService $service): void
    {
        $service->review(MonthlySchedule::findOrFail($this->selectedScheduleId), 'web');
        session()->flash('status', 'Schedule marked for approval.');
    }

    public function approve(ScheduleApprovalService $service): void
    {
        $service->approve(MonthlySchedule::findOrFail($this->selectedScheduleId), 'web');
        session()->flash('status', 'Schedule approved.');
    }

    public function lock(ScheduleLockService $service): void
    {
        $service->lock(MonthlySchedule::findOrFail($this->selectedScheduleId), 'web');
        session()->flash('status', 'Schedule locked.');
    }

    private function calendar(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        return $schedule->assignments
            ->groupBy(fn ($assignment) => $assignment->schedule_date->toDateString())
            ->map(fn ($assignments) => $assignments->take(8)->map(fn ($assignment) => [
                'employee_id' => $assignment->employee_id,
                'code' => $assignment->shiftCode?->code,
                'night' => (bool) $assignment->shiftCode?->is_night_shift,
            ])->values()->all())
            ->all();
    }
}
