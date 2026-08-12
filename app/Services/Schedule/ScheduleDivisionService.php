<?php

namespace App\Services\Schedule;

use App\Models\Hris\Department;
use Illuminate\Support\Collection;

class ScheduleDivisionService
{
    public function cnoDivisionId(): int
    {
        return (int) config('schedule.cno_division_id', 3);
    }

    public function isCnoDivision(int|string|null $divisionId): bool
    {
        if ($divisionId === null || $divisionId === '') {
            return false;
        }

        return (int) $divisionId === $this->cnoDivisionId();
    }

    public function divisionIdForDepartment(int|string|null $departmentId): ?int
    {
        if ($departmentId === null || $departmentId === '') {
            return null;
        }

        $value = Department::query()
            ->where('department_id', $departmentId)
            ->value('division_id');

        return $value !== null ? (int) $value : null;
    }

    public function isCnoDepartment(int|string|null $departmentId): bool
    {
        return $this->isCnoDivision($this->divisionIdForDepartment($departmentId));
    }

    /**
     * @return array{
     *   uses_units: bool,
     *   uses_floaters: bool,
     *   uses_on_call: bool,
     *   uses_swaps: bool,
     *   uses_census: bool
     * }
     */
    public function profileDefaultsForDepartment(int|string|null $departmentId): array
    {
        if ($this->isCnoDepartment($departmentId)) {
            return $this->cnoProfileDefaults();
        }

        return $this->simpleProfileDefaults();
    }

    /**
     * @return array{
     *   uses_units: bool,
     *   uses_floaters: bool,
     *   uses_on_call: bool,
     *   uses_swaps: bool,
     *   uses_census: bool
     * }
     */
    public function cnoProfileDefaults(): array
    {
        return [
            'uses_units' => (bool) config('schedule.cno_profile_defaults.uses_units', true),
            'uses_floaters' => (bool) config('schedule.cno_profile_defaults.uses_floaters', true),
            'uses_on_call' => (bool) config('schedule.cno_profile_defaults.uses_on_call', true),
            'uses_swaps' => (bool) config('schedule.cno_profile_defaults.uses_swaps', true),
            'uses_census' => (bool) config('schedule.cno_profile_defaults.uses_census', true),
        ];
    }

    /**
     * @return array{
     *   uses_units: bool,
     *   uses_floaters: bool,
     *   uses_on_call: bool,
     *   uses_swaps: bool,
     *   uses_census: bool
     * }
     */
    public function simpleProfileDefaults(): array
    {
        return [
            'uses_units' => (bool) config('schedule.simple_profile_defaults.uses_units', false),
            'uses_floaters' => (bool) config('schedule.simple_profile_defaults.uses_floaters', false),
            'uses_on_call' => (bool) config('schedule.simple_profile_defaults.uses_on_call', false),
            'uses_swaps' => (bool) config('schedule.simple_profile_defaults.uses_swaps', false),
            'uses_census' => (bool) config('schedule.simple_profile_defaults.uses_census', false),
        ];
    }

    /**
     * Human label for schedule mode shown in profile / dashboard hints.
     */
    public function modeLabelForDepartment(int|string|null $departmentId): string
    {
        return $this->isCnoDepartment($departmentId)
            ? 'CNO / Nursing'
            : 'Department + areas';
    }

    /**
     * Noun for ScheduleUnit in UI (ward/unit vs multi-area office).
     */
    public function unitNoun(int|string|null $departmentId, bool $plural = false): string
    {
        if ($this->isCnoDepartment($departmentId)) {
            return $plural ? 'Units' : 'Unit';
        }

        return $plural ? 'Areas' : 'Area';
    }

    /**
     * @return list<int>
     */
    public function departmentIdsForDivision(int $divisionId): array
    {
        return Department::query()
            ->where('division_id', $divisionId)
            ->orderBy('department_id')
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Department>
     */
    public function departmentsForDivision(int $divisionId): Collection
    {
        return Department::query()
            ->where('division_id', $divisionId)
            ->orderBy('department')
            ->get(['department_id', 'department', 'division_id']);
    }

    /**
     * @return array<int, int> department_id => division_id
     */
    public function divisionIdsByDepartment(array $departmentIds = []): array
    {
        $query = Department::query()->whereNotNull('division_id');
        if ($departmentIds !== []) {
            $query->whereIn('department_id', $departmentIds);
        }

        return $query
            ->pluck('division_id', 'department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
