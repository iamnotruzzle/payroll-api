<?php

namespace App\Services\Hris;

use App\Models\Hris\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PositionService
{
    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?int $salaryGrade = null,
        string $sort = 'position_title',
        string $direction = 'asc'
    ): LengthAwarePaginator {
        $query = Position::query();

        if ($salaryGrade !== null) {
            $query->where('salary_grade', $salaryGrade);
        }

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('position_title', 'like', "%{$term}%")
                    ->orWhere('remarks', 'like', "%{$term}%");

                if (is_numeric($term)) {
                    $num = (int) $term;
                    $q->orWhere('position_id', $num)
                        ->orWhere('salary_grade', $num);
                }
            });
        }

        $query->orderBy(match ($sort) {
            'position_id'  => 'position_id',
            'salary_grade' => 'salary_grade',
            default        => 'position_title',
        }, $direction === 'descending' ? 'desc' : 'asc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function salaryGrades(): Collection
    {
        return Position::query()
            ->select('salary_grade')
            ->distinct()
            ->orderBy('salary_grade')
            ->pluck('salary_grade');
    }
}
