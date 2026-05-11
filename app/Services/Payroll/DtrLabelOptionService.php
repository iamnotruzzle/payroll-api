<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollDtrLabelOption;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DtrLabelOptionService
{
    public function paginate(int $page, int $perPage): LengthAwarePaginator
    {
        return PayrollDtrLabelOption::orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function save(array $data, int $id = 0): PayrollDtrLabelOption
    {
        $option = $id ? PayrollDtrLabelOption::findOrFail($id) : new PayrollDtrLabelOption();
        $option->fill($data);
        $option->save();
        return $option;
    }
}
