<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleDepartmentProfile;
use App\Models\Schedule\ScheduleUnit;
use App\Models\Schedule\ScheduleUserUnit;
use Illuminate\Support\Collection;

class ScheduleScopeService
{
    public function __construct(
        private readonly ScheduleDivisionService $divisionService
    ) {}

    /**
     * Null = unrestricted (no handled-unit rows). Otherwise unit IDs the scheduler may manage.
     *
     * @return list<int>|null
     */
    public function handledUnitIds(?string $empId, int|string|null $departmentId): ?array
    {
        if ($empId === null || $empId === '' || $departmentId === null) {
            return null;
        }

        $profile = ScheduleDepartmentProfile::forDepartment($departmentId);
        if (! $profile->uses_units) {
            return null;
        }

        $unitIds = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->pluck('id');

        if ($unitIds->isEmpty()) {
            return null;
        }

        $handled = ScheduleUserUnit::query()
            ->where('emp_id', $empId)
            ->whereIn('schedule_unit_id', $unitIds)
            ->pluck('schedule_unit_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $handled === [] ? null : $handled;
    }

    public function unitsForDepartment(int|string|null $departmentId, bool $activeOnly = true): Collection
    {
        if ($departmentId === null) {
            return collect();
        }

        return ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function profileForDepartment(int|string|null $departmentId): ScheduleDepartmentProfile
    {
        if ($departmentId === null) {
            return new ScheduleDepartmentProfile(
                $this->divisionService->simpleProfileDefaults()
            );
        }

        return ScheduleDepartmentProfile::ensureForDepartment($departmentId);
    }

    public function divisionService(): ScheduleDivisionService
    {
        return $this->divisionService;
    }

    public function isCnoDepartment(int|string|null $departmentId): bool
    {
        return $this->divisionService->isCnoDepartment($departmentId);
    }

    public function modeLabelForDepartment(int|string|null $departmentId): string
    {
        return $this->divisionService->modeLabelForDepartment($departmentId);
    }

    public function unitNoun(int|string|null $departmentId, bool $plural = false): string
    {
        return $this->divisionService->unitNoun($departmentId, $plural);
    }
}
