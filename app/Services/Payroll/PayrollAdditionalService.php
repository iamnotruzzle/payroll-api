<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollAdditional;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayrollAdditionalService
{
    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?bool $isPercentage = null,
        ?bool $isActive = null,
        string $sort = 'name',
        string $direction = 'asc'
    ): LengthAwarePaginator {
        $query = PayrollAdditional::query()
            ->when($isPercentage !== null, fn($q) => $q->where('is_percentage', $isPercentage))
            ->when($isActive !== null, fn($q) => $q->where('is_active', $isActive))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                    if (is_numeric($search)) {
                        $q2->orWhere('id', (int) $search)
                           ->orWhere('value', (float) $search);
                    }
                });
            });

        $query->orderBy(match ($sort) {
            'id'    => 'id',
            'value' => 'value',
            default => 'name',
        }, $direction === 'descending' ? 'desc' : 'asc');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function save(array $data, int $id = 0): PayrollAdditional
    {
        $item = $id ? PayrollAdditional::findOrFail($id) : new PayrollAdditional();
        $item->fill($data);
        $item->save();
        return $item;
    }

    public function delete(int $id): bool
    {
        return PayrollAdditional::findOrFail($id)->delete();
    }
}
