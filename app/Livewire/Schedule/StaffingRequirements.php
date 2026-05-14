<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\RotationGroup;
use App\Models\Schedule\ShiftCode;
use App\Models\Schedule\StaffingRequirement;
use App\Services\Schedule\StaffingRequirementService;
use Illuminate\Support\Collection;
use Livewire\Component;

class StaffingRequirements extends Component
{
    public ?int $editingId = null;
    public ?int $rotation_group_id = null;
    public ?int $shift_code_id = null;
    public ?int $day_of_week = null;
    public int $minimum_staff = 1;
    public ?int $maximum_staff = null;
    public ?string $effective_from = null;
    public ?string $effective_to = null;
    public bool $is_active = true;

    public function render()
    {
        return view('livewire.schedule.staffing-requirements', [
            'department' => auth()->user()?->employee?->department,
            'requirements' => StaffingRequirement::with('shiftCode', 'rotationGroup')
                ->where('department_id', $this->departmentId())
                ->orderBy('rotation_group_id')
                ->orderBy('day_of_week')
                ->orderBy('shift_code_id')
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
            'days' => $this->days(),
            'weeklyPlans' => $this->weeklyPlans(),
        ]);
    }

    public function edit(int $id): void
    {
        $requirement = StaffingRequirement::where('department_id', $this->departmentId())->findOrFail($id);
        $this->editingId = $requirement->id;
        $this->rotation_group_id = $requirement->rotation_group_id;
        $this->shift_code_id = $requirement->shift_code_id;
        $this->day_of_week = $requirement->day_of_week;
        $this->minimum_staff = $requirement->minimum_staff;
        $this->maximum_staff = $requirement->maximum_staff;
        $this->effective_from = $requirement->effective_from?->format('Y-m-d');
        $this->effective_to = $requirement->effective_to?->format('Y-m-d');
        $this->is_active = $requirement->is_active;
    }

    public function save(StaffingRequirementService $service): void
    {
        $data = $this->validate([
            'rotation_group_id' => ['nullable', 'integer', 'exists:payroll_scheduler.rotation_groups,id'],
            'shift_code_id' => ['required', 'integer', 'exists:payroll_scheduler.shift_codes,id'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'minimum_staff' => ['required', 'integer', 'min:1', 'max:999'],
            'maximum_staff' => ['nullable', 'integer', 'min:1', 'max:999', 'gte:minimum_staff'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $data['department_id'] = $this->departmentId();
        $requirement = $this->editingId
            ? StaffingRequirement::where('department_id', $this->departmentId())->findOrFail($this->editingId)
            : null;

        $service->save($data, $requirement, auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Staffing requirement saved.');
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->rotation_group_id = null;
        $this->shift_code_id = null;
        $this->day_of_week = null;
        $this->minimum_staff = 1;
        $this->maximum_staff = null;
        $this->effective_from = null;
        $this->effective_to = null;
        $this->is_active = true;
    }

    public function dayName(?int $day): string
    {
        return $day === null ? 'Every day' : $this->days()[$day];
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    private function days(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    private function weeklyPlans(): array
    {
        $groups = RotationGroup::with('members')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
            })
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            return [];
        }

        $requirements = StaffingRequirement::with('shiftCode')
            ->where('department_id', $this->departmentId())
            ->where('is_active', true)
            ->whereNotNull('rotation_group_id')
            ->get()
            ->filter(fn (StaffingRequirement $requirement) => (bool) $requirement->shiftCode?->is_work_shift);

        $employeeIds = $groups
            ->flatMap(fn (RotationGroup $group) => $group->members->pluck('employee_id'))
            ->unique()
            ->values();
        $employees = Employee::query()
            ->whereIn('emp_id', $employeeIds)
            ->get(['emp_id', 'firstname', 'middlename', 'lastname'])
            ->keyBy('emp_id');
        $workShifts = ShiftCode::where('is_active', true)
            ->where('is_work_shift', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->departmentId());
            })
            ->orderByDesc('work_hours')
            ->orderBy('code')
            ->get();
        $suggestionShiftIds = $requirements->pluck('shift_code_id')->unique()->values();
        $suggestionShifts = $suggestionShiftIds->isNotEmpty()
            ? $workShifts->whereIn('id', $suggestionShiftIds->all())->values()
            : $workShifts;

        return $groups
            ->map(function (RotationGroup $group) use ($requirements, $employees, $suggestionShifts) {
                $members = $group->members
                    ->sortBy('rotation_order')
                    ->pluck('employee_id')
                    ->filter(fn ($employeeId) => $employees->has($employeeId))
                    ->values();
                $groupRequirements = $requirements
                    ->where('rotation_group_id', $group->id)
                    ->values();

                if ($members->isEmpty() || $groupRequirements->isEmpty()) {
                    return null;
                }

                $hours = $members->mapWithKeys(fn ($employeeId) => [$employeeId => 0.0])->all();
                $assignments = $members->mapWithKeys(fn ($employeeId) => [$employeeId => []])->all();
                $hasTwelveHourShift = $members->mapWithKeys(fn ($employeeId) => [$employeeId => false])->all();
                $requiredHours = 0.0;

                foreach (array_keys($this->days()) as $day) {
                    $taken = [];
                    foreach ($groupRequirements->filter(fn (StaffingRequirement $requirement) => $requirement->day_of_week === null || (int) $requirement->day_of_week === $day)->sortBy(fn (StaffingRequirement $requirement) => (float) ($requirement->shiftCode?->work_hours ?? 0)) as $requirement) {
                        $needed = min((int) $requirement->minimum_staff, $members->count());
                        $requiredHours += $needed * (float) ($requirement->shiftCode?->work_hours ?? 0);

                        while ($needed > 0) {
                            $employeeId = $this->lowestHourAvailableMember($members, $taken, $hours);
                            if (! $employeeId) {
                                break;
                            }

                            $shiftHours = (float) ($requirement->shiftCode?->work_hours ?? 0);
                            $hours[$employeeId] += $shiftHours;
                            $assignments[$employeeId][] = $this->days()[$day].' '.$requirement->shiftCode->code;
                            $hasTwelveHourShift[$employeeId] = $hasTwelveHourShift[$employeeId] || $shiftHours >= 12;
                            $taken[$employeeId] = true;
                            $needed--;
                        }
                    }
                }

                $capacityHours = $members->count() * 40;
                $capacityDelta = round($requiredHours - $capacityHours, 2);

                return [
                    'group' => $group->name,
                    'required_hours' => round($requiredHours, 2),
                    'capacity_hours' => round($capacityHours, 2),
                    'capacity_delta' => $capacityDelta,
                    'members' => $members
                        ->map(function ($employeeId) use ($employees, $hours, $assignments, $suggestionShifts, $hasTwelveHourShift) {
                            $targetMin = $hasTwelveHourShift[$employeeId] ? 36 : 40;
                            $targetMax = $hasTwelveHourShift[$employeeId] ? 48 : 40;
                            $gap = max(0, $targetMin - $hours[$employeeId]);

                            return [
                                'employee_id' => $employeeId,
                                'employee_name' => $this->employeeName($employees->get($employeeId)),
                                'hours' => round($hours[$employeeId], 2),
                                'target' => $targetMin === $targetMax ? (string) $targetMin : $targetMin.'-'.$targetMax,
                                'gap' => round($gap, 2),
                                'is_ok' => $hours[$employeeId] >= $targetMin && $hours[$employeeId] <= $targetMax,
                                'assignments' => $assignments[$employeeId],
                                'suggestions' => $this->suggestShifts($gap, $suggestionShifts),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function lowestHourAvailableMember(Collection $members, array $taken, array $hours): ?string
    {
        return $members
            ->reject(fn ($employeeId) => isset($taken[$employeeId]))
            ->sortBy(fn ($employeeId) => $hours[$employeeId] ?? 0)
            ->first();
    }

    private function suggestShifts(float $gap, Collection $workShifts): array
    {
        if ($gap <= 0 || $workShifts->isEmpty()) {
            return [];
        }

        $suggestions = [];
        $remaining = $gap;

        while ($remaining > 0 && count($suggestions) < 7) {
            $shift = $workShifts
                ->filter(fn (ShiftCode $shiftCode) => (float) $shiftCode->work_hours > 0 && (float) $shiftCode->work_hours <= $remaining)
                ->sortByDesc(fn (ShiftCode $shiftCode) => (float) $shiftCode->work_hours)
                ->first()
                ?? $workShifts->sortBy(fn (ShiftCode $shiftCode) => (float) $shiftCode->work_hours)->first();

            if (! $shift || (float) $shift->work_hours <= 0) {
                break;
            }

            $suggestions[] = [
                'code' => $shift->code,
                'name' => $shift->name,
                'hours' => (float) $shift->work_hours,
            ];
            $remaining -= (float) $shift->work_hours;
        }

        return $suggestions;
    }

    public function employeeName(?Employee $employee): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        $middleInitial = $employee->middlename ? mb_substr($employee->middlename, 0, 1).'.' : null;

        return implode(' ', array_filter([$employee->lastname.',', $employee->firstname, $middleInitial]));
    }
}
