<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\ScheduleFloaterPoolMember;
use App\Models\Schedule\ScheduleMonthlyFloater;
use App\Services\Schedule\ScheduleScopeService;
use Livewire\Component;

class ScheduleFloaters extends Component
{
    public string $search = '';

    public string $emp_id = '';

    public ?int $unit_id = null;

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?string $notes = null;

    public int $monthYear;

    public int $month;

    public string $monthly_emp_id = '';

    public ?int $monthly_unit_id = null;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.view'), 403);
        $profile = $scopeService->profileForDepartment($this->departmentId());
        abort_unless($profile->uses_floaters, 404);

        $today = now();
        $this->monthYear = (int) $today->year;
        $this->month = (int) $today->month;
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = $this->departmentId();

        $pool = ScheduleFloaterPoolMember::query()
            ->with('unit')
            ->where('department_id', $departmentId)
            ->when($this->search !== '', function ($query) {
                $query->where('emp_id', 'like', "%{$this->search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('emp_id')
            ->get();

        $monthly = ScheduleMonthlyFloater::query()
            ->with('unit')
            ->where('department_id', $departmentId)
            ->where('year', $this->monthYear)
            ->where('month', $this->month)
            ->orderBy('emp_id')
            ->get();

        $employees = Employee::query()
            ->where('department_id', $departmentId)
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(500)
            ->get(['emp_id', 'firstname', 'middlename', 'lastname']);

        return view('livewire.schedule.schedule-floaters', [
            'department' => auth()->user()?->employee?->department,
            'pool' => $pool,
            'monthly' => $monthly,
            'employees' => $employees,
            'unitOptions' => $scopeService->unitsForDepartment($departmentId),
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
            'names' => $employees->keyBy('emp_id'),
        ]);
    }

    public function addToPool(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $data = $this->validate([
            'emp_id' => ['required', 'string', 'max:30'],
            'unit_id' => ['nullable', 'integer'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        ScheduleFloaterPoolMember::query()->updateOrCreate(
            [
                'department_id' => $departmentId,
                'emp_id' => $data['emp_id'],
            ],
            [
                'unit_id' => $data['unit_id'] ?: null,
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
                'notes' => $data['notes'],
            ]
        );

        $this->emp_id = '';
        $this->notes = null;
        session()->flash('status', 'Floater pool member saved.');
    }

    public function removeFromPool(int $id): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        ScheduleFloaterPoolMember::query()
            ->where('department_id', $this->departmentId())
            ->whereKey($id)
            ->delete();
        session()->flash('status', 'Removed from floater pool.');
    }

    public function addMonthly(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $data = $this->validate([
            'monthly_emp_id' => ['required', 'string', 'max:30'],
            'monthly_unit_id' => ['nullable', 'integer'],
            'monthYear' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        ScheduleMonthlyFloater::query()->updateOrCreate(
            [
                'department_id' => $departmentId,
                'year' => $data['monthYear'],
                'month' => $data['month'],
                'emp_id' => $data['monthly_emp_id'],
                'unit_id' => $data['monthly_unit_id'] ?: null,
            ],
            ['notes' => null]
        );

        $this->monthly_emp_id = '';
        session()->flash('status', 'Monthly floater saved.');
    }

    public function removeMonthly(int $id): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        ScheduleMonthlyFloater::query()
            ->where('department_id', $this->departmentId())
            ->whereKey($id)
            ->delete();
        session()->flash('status', 'Monthly floater removed.');
    }

    private function departmentId(): ?int
    {
        $id = auth()->user()?->employee?->department_id;

        return $id !== null ? (int) $id : null;
    }
}
