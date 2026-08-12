<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\IpcrCalibrationSet;
use App\Models\Hris\IpcrEmployee;
use App\Models\Hris\IpcrMfo;
use App\Models\Hris\IpcrMfoSet;
use App\Models\Hris\IpcrPeriod;
use App\Models\Hris\IpcrRating;
use App\Models\Hris\IpcrType;
use App\Models\Hris\Opcr;
use App\Models\Hris\OpcrAccountable;
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
            ->with(['mfoSet.mfo.functionType', 'ratings', 'ipcrType', 'calibrations', 'opcr.accountables.employee'])
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
            )->fresh(['mfoSet.mfo.functionType', 'ratings', 'calibrations', 'opcr.accountables']);
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

    /**
     * @param  array{quality?:int|string|null,effectiveness?:int|string|null,timeliness?:int|string|null,notes?:?string}  $payload
     */
    public function upsertCalibration(IpcrEmployee $ipcr, array $payload): void
    {
        $mfoId = (int) ($ipcr->mfoSet?->mfo_id ?? 0);
        $map = [
            'quality' => $payload['quality'] ?? null,
            'effectiveness' => $payload['effectiveness'] ?? null,
            'timeliness' => $payload['timeliness'] ?? null,
        ];

        foreach ($map as $type => $score) {
            if ($score === null || $score === '') {
                continue;
            }

            IpcrCalibrationSet::query()->updateOrCreate(
                [
                    'ipcr_employee_id' => $ipcr->id,
                    'calibration_type' => $type,
                ],
                [
                    'score' => (int) $score,
                    'calibration' => $payload['notes'] ?? null,
                    'mfo_id' => $mfoId > 0 ? $mfoId : null,
                ]
            );
        }
    }

    /**
     * @param  list<string>  $accountableEmpIds
     */
    public function upsertOpcr(IpcrEmployee $ipcr, ?float $budget, string $actorEmpId, array $accountableEmpIds = []): Opcr
    {
        return DB::connection('hris')->transaction(function () use ($ipcr, $budget, $actorEmpId, $accountableEmpIds) {
            $opcr = Opcr::query()->updateOrCreate(
                ['ipcr_id' => $ipcr->id],
                [
                    'budget' => $budget,
                    'entry_by' => $actorEmpId,
                ]
            );

            OpcrAccountable::query()->where('opcr_id', $opcr->id)->delete();
            foreach (array_unique(array_filter($accountableEmpIds)) as $empId) {
                OpcrAccountable::query()->create([
                    'opcr_id' => $opcr->id,
                    'emp_id' => (string) $empId,
                ]);
            }

            return $opcr->fresh(['accountables.employee']);
        });
    }

    /**
     * Weighted Strategic 40% + Core 50% + Support 10% (or Core 80% + Support 20% if no strategic).
     *
     * @param  Collection<int, IpcrEmployee>  $targets
     * @return array{average:?float,grade:?string,by_function:array<string,float>,counts:array<string,int>}
     */
    public function weightedSummary(Collection $targets): array
    {
        $buckets = [
            'strategic' => [],
            'core' => [],
            'support' => [],
        ];

        foreach ($targets as $target) {
            $key = $this->functionBucket($target->mfoSet?->mfo?->functionType?->function_type);
            $score = $this->targetScore($target);
            if ($score !== null) {
                $buckets[$key][] = $score;
            }
        }

        $averages = [];
        foreach ($buckets as $key => $scores) {
            $averages[$key] = $scores === [] ? null : array_sum($scores) / count($scores);
        }

        $hasStrategic = $averages['strategic'] !== null;
        $hasCore = $averages['core'] !== null;
        $hasSupport = $averages['support'] !== null;

        $weighted = null;
        if ($hasStrategic || $hasCore || $hasSupport) {
            if ($hasStrategic) {
                $weighted = (($averages['strategic'] ?? 0) * 0.4)
                    + (($averages['core'] ?? 0) * 0.5)
                    + (($averages['support'] ?? 0) * 0.1);
                if (! $hasCore && ! $hasSupport) {
                    $weighted = $averages['strategic'];
                } elseif (! $hasCore) {
                    $weighted = (($averages['strategic'] ?? 0) * 0.4) + (($averages['support'] ?? 0) * 0.6);
                } elseif (! $hasSupport) {
                    $weighted = (($averages['strategic'] ?? 0) * 0.4) + (($averages['core'] ?? 0) * 0.6);
                }
            } else {
                $weighted = (($averages['core'] ?? 0) * 0.8) + (($averages['support'] ?? 0) * 0.2);
                if (! $hasCore) {
                    $weighted = $averages['support'];
                } elseif (! $hasSupport) {
                    $weighted = $averages['core'];
                }
            }
        }

        return [
            'average' => $weighted !== null ? round($weighted, 2) : null,
            'grade' => $this->grade($weighted),
            'by_function' => array_map(
                fn ($value) => $value !== null ? round($value, 2) : null,
                $averages
            ),
            'counts' => [
                'strategic' => count($buckets['strategic']),
                'core' => count($buckets['core']),
                'support' => count($buckets['support']),
            ],
        ];
    }

    public function grade(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        return match (true) {
            $average >= 4.5 => 'O',
            $average >= 3.5 => 'VS',
            $average >= 2.5 => 'S',
            $average >= 1.5 => 'US',
            default => 'P',
        };
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

    private function functionBucket(?string $label): string
    {
        $raw = strtolower(trim((string) $label));
        if (str_contains($raw, 'strategic')) {
            return 'strategic';
        }
        if (str_contains($raw, 'support')) {
            return 'support';
        }

        return 'core';
    }

    private function targetScore(IpcrEmployee $target): ?float
    {
        $calibrations = $target->calibrations ?? collect();
        if ($calibrations->isNotEmpty()) {
            $scores = $calibrations->pluck('score')->filter(fn ($s) => $s !== null)->map(fn ($s) => (float) $s);
            if ($scores->isNotEmpty()) {
                return round($scores->avg(), 2);
            }
        }

        $rating = $target->ratings?->first();
        if (! $rating) {
            return null;
        }

        $parts = collect([$rating->quality, $rating->effectiveness, $rating->timeliness])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (float) $v);

        return $parts->isEmpty() ? null : round($parts->avg(), 2);
    }
}
