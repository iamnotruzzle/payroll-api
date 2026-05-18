<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\RotationGroup;
use App\Services\Schedule\RotationGroupService;
use Livewire\Component;

class RotationGroups extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?string $description = null;

    public bool $is_active = true;

    public array $members = [];

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public function mount(): void
    {
        $this->members = [''];
    }

    public function render()
    {
        return view('livewire.schedule.rotation-groups', [
            'groups' => RotationGroup::with('members')
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
                })
                ->orderBy('name')
                ->get(),
            'employees' => Employee::query()
                ->where('department_id', $this->departmentId())
                ->where('is_active', 'Y')
                ->employeeType($this->employeeTypeFilter)
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['emp_id', 'firstname', 'middlename', 'lastname']),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
        ]);
    }

    public function edit(int $id): void
    {
        $group = RotationGroup::with('members')
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
            })
            ->findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description;
        $this->is_active = $group->is_active;
        $this->members = $group->members
            ->sortBy('rotation_order')
            ->pluck('employee_id')
            ->values()
            ->all() ?: [''];
    }

    public function save(RotationGroupService $service): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'members' => ['array'],
            'members.*' => ['nullable', 'string', 'max:50'],
        ]);

        $data['id'] = $this->editingId;
        $data['department_id'] = $this->departmentId();
        $data['members'] = collect($data['members'])->filter()->unique()->values()->all();

        $service->save($data, auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Rotation group saved.');
        $this->resetForm();
    }

    public function addMember(): void
    {
        $this->members[] = '';
    }

    public function removeMember(int $index): void
    {
        unset($this->members[$index]);
        $this->members = array_values($this->members ?: ['']);
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = null;
        $this->is_active = true;
        $this->members = [''];
    }

    public function employeeName(?Employee $employee): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        $middleInitial = $employee->middlename ? mb_substr($employee->middlename, 0, 1).'.' : null;

        return implode(' ', array_filter([$employee->lastname.',', $employee->firstname, $middleInitial]));
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
