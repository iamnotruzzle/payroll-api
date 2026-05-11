<?php

namespace App\Services\Payroll;

use App\Models\Payroll\Holiday;
use App\Models\Payroll\PayrollHoliday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HolidayService
{
    public function paginate(int $page, int $perPage): LengthAwarePaginator
    {
        return PayrollHoliday::orderByDesc('holiday_date')
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function save(array $data, int $id = 0): PayrollHoliday
    {
        $holiday = $id ? PayrollHoliday::findOrFail($id) : new PayrollHoliday();
        $holiday->fill($data);
        $holiday->save();
        return $holiday;
    }

    public function duplicateExists(string $date, int $excludeId = 0): bool
    {
        return PayrollHoliday::where('holiday_date', $date)
            ->where('id', '!=', $excludeId)
            ->exists();
    }
}
