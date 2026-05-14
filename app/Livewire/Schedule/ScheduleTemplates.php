<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\RotationGroup;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ScheduleTemplateService;
use Livewire\Component;

class ScheduleTemplates extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public ?int $rotation_group_id = null;
    public bool $is_active = true;
    public array $days = [];

    public function mount(): void
    {
        $this->days = [''];
    }

    public function render()
    {
        return view('livewire.schedule.schedule-templates', [
            'department' => auth()->user()?->employee?->department,
            'templates' => ScheduleTemplate::with('days.shiftCode', 'rotationGroup')
                ->where('department_id', $this->departmentId())
                ->orderBy('name')
                ->get(),
            'rotationGroups' => RotationGroup::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'shiftCodes' => ShiftCode::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
                })
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function edit(int $id): void
    {
        $template = ScheduleTemplate::with('days')->where('department_id', $this->departmentId())->findOrFail($id);
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->rotation_group_id = $template->rotation_group_id;
        $this->is_active = $template->is_active;
        $this->days = $template->days->pluck('shift_code_id')->values()->all() ?: [''];
    }

    public function save(ScheduleTemplateService $service): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'rotation_group_id' => ['nullable', 'integer', 'exists:payroll_scheduler.rotation_groups,id'],
            'is_active' => ['boolean'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'integer', 'exists:payroll_scheduler.shift_codes,id'],
        ]);

        $data['id'] = $this->editingId;
        $data['department_id'] = $this->departmentId();

        $service->save($data, auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Schedule template saved.');
        $this->resetForm();
    }

    public function addDay(): void
    {
        $this->days[] = '';
    }

    public function removeDay(int $index): void
    {
        unset($this->days[$index]);
        $this->days = array_values($this->days ?: ['']);
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->rotation_group_id = null;
        $this->is_active = true;
        $this->days = [''];
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
