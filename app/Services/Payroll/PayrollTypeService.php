<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayrollTypeService
{
    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        string $sort = 'name',
        string $direction = 'asc'
    ): LengthAwarePaginator {
        $query = PayrollType::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $q2->orWhere('id', (int) $search);
                    }
                });
            });

        $query->orderBy(match ($sort) {
            'id' => 'id',
            'code' => 'code',
            default => 'name',
        }, $direction === 'descending' ? 'desc' : 'asc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
