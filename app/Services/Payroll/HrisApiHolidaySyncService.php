<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollHoliday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class HrisApiHolidaySyncService
{
    public function sync(string $period): array
    {
        abort_unless(app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone, 403);
        Validator::make(['period' => $period], ['period' => ['required', 'date_format:Y-m']])->validate();
        if (! Schema::connection('payroll')->hasColumn('payroll_holidays', 'hris_holiday_id')) {
            throw new RuntimeException('Run the payroll database migrations before synchronizing holidays.');
        }
        $result = app(HrisApiClient::class)->fetch('holidays', ['period' => $period]);
        if ($result['warning_count']) {
            throw new RuntimeException('HRIS returned calendar warnings. Existing calendar entries were retained.');
        }
        $entries = [];
        $skipped = 0;
        $seenDates = [];
        $seenIds = [];
        foreach ($result['rows'] as $row) {
            Validator::make($row, [
                'holiday_id' => ['required', 'integer', 'min:1'], 'date' => ['required', 'date_format:Y-m-d'],
                'kind' => ['required', 'in:holiday,suspension,od_only'],
                'whole_day' => ['required', 'boolean'], 'closes_work' => ['required', 'boolean'],
            ])->validate();
            if (! str_starts_with($row['date'], $period.'-') || isset($seenIds[$row['holiday_id']])) {
                throw new RuntimeException('HRIS returned a duplicate calendar ID or a date outside the selected month. Existing entries were retained.');
            }
            $seenIds[$row['holiday_id']] = true;
            if ($row['kind'] === 'od_only' || ! $row['closes_work']) {
                $skipped++;
                continue;
            }
            $scope = $row['whole_day'] ? 'FULL_DAY' : match (true) {
                in_array($row['time_from'], ['12:00:00', '13:00:00'], true) && $row['time_to'] === null => 'SECOND_HALF',
                $row['time_from'] === null && in_array($row['time_to'], ['12:00:00', '13:00:00'], true) => 'FIRST_HALF',
                default => null,
            };
            if ($scope === null) {
                throw new RuntimeException('A calendar entry has unsupported partial-day hours. Add it manually; existing calendar entries were retained.');
            }
            Validator::make($row, ['name' => ['required', 'string', 'max:255'],
                'holiday_type' => $row['kind'] === 'holiday' ? ['required', 'in:regular,special'] : ['nullable']])->validate();
            if (isset($seenDates[$row['date']])) {
                throw new RuntimeException('HRIS returned multiple work closures on one date. Resolve these manually; existing entries were retained.');
            }
            $seenDates[$row['date']] = true;
            $entries[] = ['hris_holiday_id' => $row['holiday_id'], 'holiday_date' => $row['date'], 'name' => $row['name'],
                'holiday_type' => $row['kind'] === 'suspension' ? 'WORK_SUSPENSION' : strtoupper($row['holiday_type']),
                'holiday_scope' => $scope, 'label_code' => 'HOLIDAY', 'is_paid' => true, 'is_active' => true];
        }

        return DB::connection('payroll')->transaction(function () use ($entries, $skipped) {
            $synced = 0;
            foreach ($entries as $entry) {
                $conflict = PayrollHoliday::query()->whereDate('holiday_date', $entry['holiday_date'])
                    ->where(function ($query) use ($entry) {
                        $query->whereNull('hris_holiday_id')->orWhere('hris_holiday_id', '!=', $entry['hris_holiday_id']);
                    })->exists();
                if ($conflict) {
                    $skipped++;
                    continue;
                }
                $existing = PayrollHoliday::where('hris_holiday_id', $entry['hris_holiday_id'])->first();
                if ($existing) {
                    // Paid status is a local decision: the API does not supply it.
                    $entry['is_paid'] = $existing->is_paid;
                }
                PayrollHoliday::updateOrCreate(['hris_holiday_id' => $entry['hris_holiday_id']], $entry);
                $synced++;
            }

            return compact('synced', 'skipped');
        });
    }
}
