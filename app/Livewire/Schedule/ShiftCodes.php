<?php

namespace App\Livewire\Schedule;

use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ShiftCodeService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ShiftCodes extends Component
{
    public ?int $editingId = null;

    public string $search = '';

    public string $scopeFilter = 'office';

    public string $statusFilter = 'active';

    public string $typeFilter = 'all';

    public string $code = '';

    public string $name = '';

    public ?string $start_time = null;

    public ?string $end_time = null;

    public int $end_day_offset = 0;

    public ?string $work_hours = null;

    public bool $is_work_shift = true;

    public bool $is_night_shift = false;

    public bool $is_leave_code = false;

    public bool $is_active = true;

    public ?string $description = null;

    public string $scope = 'office';

    public bool $workHoursManuallyEdited = false;

    public function render()
    {
        return view('livewire.schedule.shift-codes', [
            'shiftCodes' => ShiftCode::query()
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
                })
                ->when($this->scopeFilter === 'office', fn ($query) => $query->where('department_id', $this->departmentId()))
                ->when($this->scopeFilter === 'global', fn ($query) => $query->whereNull('department_id'))
                ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when($this->typeFilter === 'work', fn ($query) => $query->where('is_work_shift', true))
                ->when($this->typeFilter === 'leave', fn ($query) => $query->where('is_leave_code', true))
                ->when($this->typeFilter === 'night', fn ($query) => $query->where('is_night_shift', true))
                ->when($this->typeFilter === 'non_work', fn ($query) => $query->where('is_work_shift', false))
                ->when($this->search, fn ($query) => $query->where(function ($query) {
                    $query->where('code', 'like', "%{$this->search}%")
                        ->orWhere('name', 'like', "%{$this->search}%");
                }))
                ->orderBy('code')
                ->get(),
            'department' => auth()->user()?->employee?->department,
        ]);
    }

    public function edit(int $id): void
    {
        $shift = ShiftCode::query()
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
            })
            ->findOrFail($id);
        $this->editingId = $shift->id;
        $this->code = $shift->code;
        $this->name = $shift->name;
        $this->start_time = $shift->start_time ? substr($shift->start_time, 0, 5) : null;
        $this->end_time = $shift->end_time ? substr($shift->end_time, 0, 5) : null;
        $this->end_day_offset = $shift->end_day_offset;
        $this->work_hours = $shift->work_hours !== null ? number_format((float) $shift->work_hours, 2, '.', '') : null;
        $this->is_work_shift = $shift->is_work_shift;
        $this->is_night_shift = $shift->is_night_shift;
        $this->is_leave_code = $shift->is_leave_code;
        $this->is_active = $shift->is_active;
        $this->description = $shift->description;
        $this->scope = $shift->department_id ? 'office' : 'global';
    }

    public function save(ShiftCodeService $service): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_day_offset' => ['integer', 'between:0,2'],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:72'],
            'is_work_shift' => ['boolean'],
            'is_night_shift' => ['boolean'],
            'is_leave_code' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'scope' => ['required', 'in:global,office'],
        ]);

        $data['department_id'] = $data['scope'] === 'office' ? $this->departmentId() : null;
        unset($data['scope']);

        $this->ensureCodeIsUniqueForScope($data['code'], $data['department_id']);

        $service->save(
            $data,
            $this->editingId
                ? ShiftCode::query()
                    ->where(function ($query) {
                        $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
                    })
                    ->findOrFail($this->editingId)
                : null,
            'web',
        );
        session()->flash('status', 'Shift code saved.');
        $this->resetForm();
    }

    public function seedDefaults(ShiftCodeService $service): void
    {
        $service->seedDefaults('web', $this->departmentId());
        session()->flash('status', 'Default shift codes are ready.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->start_time = null;
        $this->end_time = null;
        $this->end_day_offset = 0;
        $this->work_hours = null;
        $this->is_work_shift = true;
        $this->is_night_shift = false;
        $this->is_leave_code = false;
        $this->is_active = true;
        $this->description = null;
        $this->scope = 'office';
        $this->workHoursManuallyEdited = false;
    }

    public function updatedStartTime(): void
    {
        $this->refreshComputedWorkHours();
    }

    public function updatedEndTime(): void
    {
        $this->refreshComputedWorkHours();
    }

    public function updatedEndDayOffset(): void
    {
        $this->refreshComputedWorkHours();
    }

    public function updatedWorkHours(): void
    {
        $this->workHoursManuallyEdited = true;
    }

    private function refreshComputedWorkHours(): void
    {
        if ($this->workHoursManuallyEdited || ! $this->start_time || ! $this->end_time) {
            return;
        }

        $start = strtotime('2000-01-01 '.$this->start_time);
        $end = strtotime('2000-01-01 '.$this->end_time.' +'.$this->end_day_offset.' day');

        if ($this->end_day_offset === 0 && $end < $start) {
            $end = strtotime('2000-01-02 '.$this->end_time);
        }

        $this->work_hours = number_format(max(0, ($end - $start) / 3600), 2, '.', '');
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    private function ensureCodeIsUniqueForScope(string $code, ?int $departmentId): void
    {
        $duplicate = ShiftCode::query()
            ->when(
                $departmentId === null,
                fn ($query) => $query->whereNull('department_id'),
                fn ($query) => $query->where('department_id', $departmentId),
            )
            ->where('code', $code)
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => 'A shift code with this code already exists for the selected scope.',
            ]);
        }
    }
}
