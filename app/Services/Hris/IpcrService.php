<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\IpcrEmployee;
use App\Models\Hris\IpcrMfo;
use App\Models\Hris\IpcrMfoSet;
use App\Models\Hris\IpcrPeriod;
use App\Models\Hris\IpcrRating;
use App\Models\Hris\IpcrType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IpcrService
{
    /**
     * @param  array{year:int,period_type:string,period:string}  $payload
     */
    public function createPeriod(array $payload): IpcrPeriod
    {
        return IpcrPeriod::query()->firstOrCreate(
            [
                'year' => (int) $payload['year'],
                'period_type' => (string) $payload['period_type'],
                'period' => (string) $payload['period'],
            ]
        );
    }

    /**
     * @return Collection<int, IpcrEmployee>
     */
    public function targetsForEmployeePeriod(string $empId, int $periodId): Collection
    {
        return IpcrEmployee::query()
            ->with(['mfoSet.mfo.functionType', 'ratings', 'ipcrType'])
            ->where('emp_id', $empId)
            ->whereHas('mfoSet', fn ($q) => $q->where('period_id', $periodId))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertTarget(string $empId, int $periodId, array $payload, string $actorEmpId): IpcrEmployee
    {
        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
        $departmentId = (int) ($employee->department_id ?: 0);
        if ($departmentId <= 0) {
            throw ValidationException::withMessages(['emp_id' => 'Employee has no department for MFO set.']);
        }

        return DB::connection('hris')->transaction(function () use ($empId, $periodId, $payload, $actorEmpId, $departmentId) {
            $mfoId = (int) ($payload['mfo_id'] ?? 0);
            if ($mfoId <= 0) {
                $mfo = IpcrMfo::query()->create([
                    'mfo' => (string) $payload['mfo'],
                    'function_type_id' => (int) ($payload['function_type_id'] ?? 2),
                ]);
                $mfoId = (int) $mfo->id;
            }

            $mfoSet = IpcrMfoSet::query()->firstOrCreate(
                [
                    'mfo_id' => $mfoId,
                    'period_id' => $periodId,
                    'department_id' => $departmentId,
                ],
                [
                    'entry_by' => $actorEmpId,
                ]
            );

            $typeId = $this->ensureDefaultTypeId((int) ($payload['type_id'] ?? 0));

            return IpcrEmployee::query()->updateOrCreate(
                [
                    'emp_id' => $empId,
                    'mfo_set_id' => $mfoSet->id,
                ],
                [
                    'type_id' => $typeId,
                    'target' => (string) $payload['target'],
                    'accomplishment' => $payload['accomplishment'] ?? null,
                    'accomplishment_date' => $payload['accomplishment_date'] ?? null,
                ]
            )->fresh(['mfoSet.mfo.functionType', 'ratings']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveRating(IpcrEmployee $ipcr, string $raterEmpId, array $payload): IpcrRating
    {
        return IpcrRating::query()->updateOrCreate(
            [
                'ipcr_id' => $ipcr->id,
                'rating_by' => $raterEmpId,
            ],
            [
                'quality' => isset($payload['quality']) ? (string) $payload['quality'] : null,
                'effectiveness' => isset($payload['effectiveness']) ? (string) $payload['effectiveness'] : null,
                'timeliness' => isset($payload['timeliness']) ? (string) $payload['timeliness'] : null,
                'remarks' => $payload['remarks'] ?? null,
            ]
        );
    }

    public function ensureDefaultTypeId(int $typeId = 0): int
    {
        if ($typeId > 0 && IpcrType::query()->whereKey($typeId)->exists()) {
            return $typeId;
        }

        $existing = IpcrType::query()->orderBy('id')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $created = IpcrType::query()->create([
            'type' => 'Standard',
            'remarks' => 'Default IPCR type (auto-created by payroll-api Phase 7)',
        ]);

        return (int) $created->id;
    }
}
