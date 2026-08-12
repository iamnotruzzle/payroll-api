<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\ScheduleOnCallPoolMember;
use App\Services\Schedule\ScheduleScopeService;
use Livewire\Component;

class ScheduleOnCall extends Component
{
    public string $search = '';

    public string $emp_id = '';

    public ?int $unit_id = null;

    public bool $is_second = false;

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?string $notes = null;

    public function mount(ScheduleScopeService $scopeService): void
    {
        abort_unless(auth()->user()?->can('schedule.view'), 403);
        abort_unless($scopeService->profileForDepartment($this->departmentId())->uses_on_call, 404);
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $departmentId = $this->departmentId();

        $members = ScheduleOnCallPoolMember::query()
            ->with('unit')
            ->where('department_id', $departmentId)
            ->when($this->search !== '', fn ($q) => $q->where('emp_id', 'like', "%{$this->search}%"))
            ->orderBy('is_second')
            ->orderBy('sort_order')
            ->orderBy('emp_id')
            ->get();

        $employees = Employee::query()
            ->where('department_id', $departmentId)
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(500)
            ->get(['emp_id', 'firstname', 'middlename', 'lastname']);

        return view('livewire.schedule.schedule-on-call', [
            'department' => auth()->user()?->employee?->department,
            'primary' => $members->where('is_second', false)->values(),
            'second' => $members->where('is_second', true)->values(),
            'employees' => $employees,
            'unitOptions' => $scopeService->unitsForDepartment($departmentId),
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
            'names' => $employees->keyBy('emp_id'),
        ]);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        $departmentId = $this->departmentId();
        abort_unless($departmentId !== null, 404);

        $data = $this->validate([
            'emp_id' => ['required', 'string', 'max:30'],
            'unit_id' => ['nullable', 'integer'],
            'is_second' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        ScheduleOnCallPoolMember::query()->updateOrCreate(
            [
                'department_id' => $departmentId,
                'emp_id' => $data['emp_id'],
                'is_second' => $data['is_second'],
                'unit_id' => $data['unit_id'] ?: null,
            ],
            [
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
                'notes' => $data['notes'],
            ]
        );

        $this->emp_id = '';
        $this->notes = null;
        session()->flash('status', 'On-call pool member saved.');
    }

    public function remove(int $id): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);
        ScheduleOnCallPoolMember::query()
            ->where('department_id', $this->departmentId())
            ->whereKey($id)
            ->delete();
        session()->flash('status', 'Removed from on-call pool.');
    }

    private function departmentId(): ?int
    {
        $id = auth()->user()?->employee?->department_id;

        return $id !== null ? (int) $id : null;
    }
}
