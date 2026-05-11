<?php

namespace App\Services\Hris;

use App\Models\Hris\SalaryGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalaryGradeService
{
    public function paginate(
        int $page,
        int $perPage,
        ?int $salaryGrade = null,
        ?int $tranche = null,
        string $sort = 'effectivity_date',
        string $direction = 'desc'
    ): LengthAwarePaginator {
        $query = SalaryGrade::query();

        if ($salaryGrade !== null) {
            $query->where('salary_grade', $salaryGrade);
        }

        if ($tranche !== null) {
            $query->where('tranche_number', $tranche);
        }

        $query->orderBy(match ($sort) {
            'salary_grade_id' => 'salary_grade_id',
            'tranche_number'  => 'tranche_number',
            'salary_grade'    => 'salary_grade',
            'step_increment'  => 'step_increment',
            'salary'          => 'salary',
            default           => 'effectivity_date',
        }, $direction === 'ascending' ? 'asc' : 'desc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function salaryGradeOptions(): Collection
    {
        return SalaryGrade::query()
            ->select('salary_grade')
            ->distinct()
            ->orderBy('salary_grade')
            ->pluck('salary_grade');
    }

    public function trancheOptions(): Collection
    {
        return SalaryGrade::query()
            ->select('tranche_number')
            ->whereNotNull('tranche_number')
            ->distinct()
            ->orderBy('tranche_number')
            ->pluck('tranche_number');
    }
}
