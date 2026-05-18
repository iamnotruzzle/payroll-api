<?php

namespace Database\Seeders;

use App\Models\Payroll\PayrollHoliday;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\RotationGroup;
use App\Models\Schedule\RotationGroupMember;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\ShiftCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IhompMay2026ScheduleSeeder extends Seeder
{
    private const DEPARTMENT_ID = 2;

    private const TEMPLATE_NAME = 'IHOMP May 2026 Monthly Rotation';

    private const ROTATION_GROUP_NAME = 'IHOMP Tech Monthly Rotation Group';

    public function run(): void
    {
        DB::connection('payroll_scheduler')->transaction(function (): void {
            $shiftCodes = $this->seedShiftCodes();

            $this->seedHolidays();
            $this->deactivateHolidayShiftCodes();
            $this->removeSeededMonthlySchedule();
            $this->seedMonthlyTemplates($shiftCodes);
            $this->seedReusablePatterns($shiftCodes);
        });
    }

    /**
     * @return array<string, ShiftCode>
     */
    private function seedShiftCodes(): array
    {
        $rows = [
            '7-3' => [
                'name' => '7AM - 3PM',
                'start_time' => '07:00',
                'end_time' => '15:00',
                'work_hours' => 8,
                'is_work_shift' => true,
            ],
            '3-11' => [
                'name' => '3PM - 11PM',
                'start_time' => '15:00',
                'end_time' => '23:00',
                'work_hours' => 8,
                'is_work_shift' => true,
            ],
            '11-7' => [
                'name' => '11PM - 7AM Next Day',
                'start_time' => '23:00',
                'end_time' => '07:00',
                'end_day_offset' => 1,
                'work_hours' => 8,
                'is_work_shift' => true,
                'is_night_shift' => true,
            ],
            '8-5' => [
                'name' => '8AM - 5PM',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'work_hours' => 8,
                'is_work_shift' => true,
            ],
            'OFF' => [
                'name' => 'Off Duty',
                'is_work_shift' => false,
            ],
            'CTO' => [
                'name' => 'Compensatory Time Off',
                'is_work_shift' => false,
                'is_leave_code' => true,
            ],
            'SPL' => [
                'name' => 'Special Privilege Leave',
                'is_work_shift' => false,
                'is_leave_code' => true,
            ],
            'VL' => [
                'name' => 'Vacation Leave',
                'is_work_shift' => false,
                'is_leave_code' => true,
            ],
            'OB' => [
                'name' => 'Official Business',
                'is_work_shift' => false,
            ],
        ];

        $shiftCodes = [];

        foreach ($rows as $code => $row) {
            $shiftCodes[$code] = ShiftCode::query()->updateOrCreate(
                [
                    'department_id' => self::DEPARTMENT_ID,
                    'code' => $code,
                ],
                $row + [
                    'end_day_offset' => $row['end_day_offset'] ?? 0,
                    'is_night_shift' => $row['is_night_shift'] ?? false,
                    'is_leave_code' => $row['is_leave_code'] ?? false,
                    'is_active' => true,
                    'description' => 'Seeded from IHOMP Tech Monthly Schedule of Duty for May 2026.',
                ],
            );
        }

        return $shiftCodes;
    }

    /**
     * @param  array<string, ShiftCode>  $shiftCodes
     */
    private function seedMonthlyTemplates(array $shiftCodes): void
    {
        $this->removeOldTemplates();

        $group = RotationGroup::query()->updateOrCreate(
            [
                'department_id' => self::DEPARTMENT_ID,
                'name' => self::ROTATION_GROUP_NAME,
            ],
            [
                'description' => 'IHOMP technical staff who rotate through monthly duty patterns.',
                'is_active' => true,
            ],
        );

        $template = ScheduleTemplate::query()->updateOrCreate(
            ['name' => self::TEMPLATE_NAME],
            [
                'department_id' => self::DEPARTMENT_ID,
                'rotation_group_id' => $group->id,
                'is_active' => true,
            ],
        );

        $template->days()->delete();

        foreach (array_keys($this->assignments()) as $index => $employeeId) {
            RotationGroupMember::query()->updateOrCreate(
                [
                    'rotation_group_id' => $group->id,
                    'employee_id' => $employeeId,
                ],
                ['rotation_order' => $index],
            );

            EmployeeScheduleSetting::query()->updateOrCreate(
                ['employee_id' => $employeeId],
                [
                    'default_shift_code_id' => null,
                    'can_rotate_shift' => true,
                    'uses_regular_weekday_schedule' => false,
                    'max_consecutive_duty_days' => 31,
                    'max_night_shifts_per_month' => 31,
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->monthlyPattern() as $day => $code) {
            $template->days()->create([
                'day_sequence' => $day,
                'shift_code_id' => $shiftCodes[$code]->id,
            ]);
        }
    }

    private function seedHolidays(): void
    {
        PayrollHoliday::query()->updateOrCreate(
            ['holiday_date' => '2026-05-01'],
            [
                'name' => 'Labor Day',
                'holiday_type' => 'REGULAR',
                'holiday_scope' => 'FULL_DAY',
                'label_code' => 'HOLIDAY',
                'is_paid' => true,
                'is_active' => true,
            ],
        );
    }

    private function deactivateHolidayShiftCodes(): void
    {
        ShiftCode::query()
            ->whereIn('code', ['H', 'HOLIDAY'])
            ->where(function ($query): void {
                $query->whereNull('department_id')->orWhere('department_id', self::DEPARTMENT_ID);
            })
            ->update([
                'is_active' => false,
                'description' => 'Holiday dates are managed in payroll_holidays, not as schedule shift codes.',
            ]);
    }

    /**
     * @param  array<string, ShiftCode>  $shiftCodes
     */
    private function seedReusablePatterns(array $shiftCodes): void
    {
        $patterns = [
            'IHOMP Pattern - Regular Weekday 8-5' => [
                '8-5', '8-5', '8-5', '8-5', '8-5', 'OFF', 'OFF',
            ],
            'IHOMP Pattern - Morning Weekday 7-3' => [
                '7-3', '7-3', '7-3', '7-3', '7-3', 'OFF', 'OFF',
            ],
            'IHOMP Pattern - Afternoon Weekday 3-11' => [
                '3-11', '3-11', '3-11', '3-11', '3-11', 'OFF', 'OFF',
            ],
            'IHOMP Pattern - Night Weekday 11-7' => [
                '11-7', '11-7', '11-7', '11-7', '11-7', 'OFF', 'OFF',
            ],
            'IHOMP Pattern - Shifting Cycle' => [
                '7-3', '7-3', '3-11', '3-11', '11-7', '11-7', 'OFF',
            ],
        ];

        foreach ($patterns as $name => $codes) {
            $template = ScheduleTemplate::query()->updateOrCreate(
                ['name' => $name],
                [
                    'department_id' => self::DEPARTMENT_ID,
                    'rotation_group_id' => null,
                    'is_active' => true,
                ],
            );

            $template->days()->delete();

            foreach ($codes as $index => $code) {
                $template->days()->create([
                    'day_sequence' => $index + 1,
                    'shift_code_id' => $shiftCodes[$code]->id,
                ]);
            }
        }
    }

    private function removeOldTemplates(): void
    {
        ScheduleTemplate::query()
            ->where('department_id', self::DEPARTMENT_ID)
            ->where(function ($query): void {
                $query
                    ->where('name', 'like', 'IHOMP May 2026 Monthly - %')
                    ->orWhere('name', 'IHOMP May 2026 Monthly Rotation Patterns');
            })
            ->get()
            ->each(function (ScheduleTemplate $template): void {
                $template->days()->delete();
                $template->delete();
            });

        RotationGroup::query()
            ->where('department_id', self::DEPARTMENT_ID)
            ->where('name', 'like', 'IHOMP May 2026 - %')
            ->get()
            ->each(function (RotationGroup $group): void {
                $group->members()->delete();
                $group->delete();
            });
    }

    /**
     * A Schedule Template is monthly, so it must expose only 31 days in the UI.
     * Rotation group members move through this pattern by rotation_order/month.
     *
     * @return array<int, string>
     */
    private function monthlyPattern(): array
    {
        return $this->assignments()['000539'];
    }

    private function removeSeededMonthlySchedule(): void
    {
        $schedule = MonthlySchedule::query()
            ->where('department_id', self::DEPARTMENT_ID)
            ->where('rotation_group_id', null)
            ->where('year', 2026)
            ->where('month', 5)
            ->where('generated_by', 'ihomp-may-2026-seeder')
            ->first();

        if (! $schedule) {
            return;
        }

        ScheduleAssignment::query()
            ->where('monthly_schedule_id', $schedule->id)
            ->where('source', 'ihomp_may_2026_seed')
            ->delete();

        if ($schedule->assignments()->doesntExist()) {
            $schedule->delete();
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function assignments(): array
    {
        return [
            '000539' => [
                1 => 'OFF', 2 => 'OFF', 3 => 'OFF', 4 => '7-3', 5 => '7-3', 6 => '8-5', 7 => '8-5', 8 => '8-5', 9 => 'OFF',
                10 => 'OFF', 11 => '8-5', 12 => '8-5', 13 => '8-5', 14 => '8-5', 15 => '8-5', 16 => 'OFF',
                17 => 'OFF', 18 => '8-5', 19 => '8-5', 20 => '7-3', 21 => '7-3', 22 => '8-5', 23 => 'OFF',
                24 => 'OFF', 25 => '8-5', 26 => '8-5', 27 => '7-3', 28 => '7-3', 29 => '8-5', 30 => 'OFF', 31 => 'OFF',
            ],
            '001330' => [
                1 => 'OFF', 2 => '3-11', 3 => '3-11', 4 => '11-7', 5 => '11-7', 6 => 'OFF', 7 => 'OFF', 8 => 'CTO', 9 => 'OFF',
                10 => 'OFF', 11 => 'CTO', 12 => '3-11', 13 => '3-11', 14 => '11-7', 15 => '11-7', 16 => 'OFF',
                17 => 'OFF', 18 => '7-3', 19 => '7-3', 20 => '3-11', 21 => 'OFF', 22 => '3-11', 23 => '3-11',
                24 => '3-11', 25 => '11-7', 26 => '11-7', 27 => 'OFF', 28 => 'OFF', 29 => '7-3', 30 => 'OFF', 31 => '3-11',
            ],
            '001527' => [
                1 => '11-7', 2 => 'OFF', 3 => 'OFF', 4 => '8-5', 5 => '8-5', 6 => 'OFF', 7 => '7-3', 8 => '8-5', 9 => 'OFF',
                10 => 'OFF', 11 => '8-5', 12 => '8-5', 13 => 'OFF', 14 => '7-3', 15 => '7-3', 16 => 'OFF',
                17 => 'OFF', 18 => 'OFF', 19 => 'CTO', 20 => 'CTO', 21 => '3-11', 22 => '11-7', 23 => '11-7',
                24 => '11-7', 25 => 'OFF', 26 => 'OFF', 27 => 'OFF', 28 => 'CTO', 29 => '8-5', 30 => '3-11', 31 => '11-7',
            ],
            '002025' => [
                1 => '7-3', 2 => 'OFF', 3 => '7-3', 4 => '3-11', 5 => '3-11', 6 => '11-7', 7 => '11-7', 8 => 'OFF', 9 => 'OFF',
                10 => '7-3', 11 => '7-3', 12 => 'OFF', 13 => 'OFF', 14 => '3-11', 15 => '3-11', 16 => '11-7',
                17 => '11-7', 18 => 'OFF', 19 => 'OFF', 20 => '8-5', 21 => '8-5', 22 => '7-3', 23 => 'OFF',
                24 => 'OFF', 25 => '8-5', 26 => '8-5', 27 => '8-5', 28 => '8-5', 29 => '3-11', 30 => '11-7', 31 => 'OFF',
            ],
            '002129' => [
                1 => '3-11', 2 => '11-7', 3 => '11-7', 4 => 'OFF', 5 => 'OFF', 6 => '8-5', 7 => '8-5', 8 => '7-3', 9 => '7-3',
                10 => '3-11', 11 => '3-11', 12 => '11-7', 13 => '11-7', 14 => 'OFF', 15 => 'OFF', 16 => '7-3',
                17 => '7-3', 18 => '8-5', 19 => 'CTO', 20 => '8-5', 21 => 'CTO', 22 => 'OFF', 23 => 'OFF',
                24 => 'OFF', 25 => '3-11', 26 => '3-11', 27 => '11-7', 28 => 'OFF', 29 => 'OFF', 30 => '7-3', 31 => 'OFF',
            ],
            '002745' => [
                1 => 'OFF', 2 => 'OFF', 3 => 'OFF', 4 => '8-5', 5 => '8-5', 6 => '8-5', 7 => '8-5', 8 => '8-5', 9 => 'OFF',
                10 => 'OFF', 11 => '8-5', 12 => '8-5', 13 => '8-5', 14 => '8-5', 15 => '8-5', 16 => 'OFF',
                17 => 'OFF', 18 => '8-5', 19 => '8-5', 20 => '8-5', 21 => '8-5', 22 => '8-5', 23 => 'OFF',
                24 => 'OFF', 25 => '8-5', 26 => '8-5', 27 => '8-5', 28 => '8-5', 29 => '8-5', 30 => 'OFF', 31 => 'OFF',
            ],
            '002746' => [
                1 => 'OFF', 2 => 'OFF', 3 => 'OFF', 4 => 'OFF', 5 => 'SPL', 6 => '3-11', 7 => '3-11', 8 => '11-7', 9 => '11-7',
                10 => 'OFF', 11 => 'OFF', 12 => '7-3', 13 => '7-3', 14 => 'OFF', 15 => 'CTO', 16 => '3-11',
                17 => '3-11', 18 => '11-7', 19 => '11-7', 20 => 'OFF', 21 => 'OFF', 22 => 'CTO', 23 => '7-3',
                24 => '7-3', 25 => 'CTO', 26 => '8-5', 27 => '3-11', 28 => '11-7', 29 => 'OFF', 30 => 'OFF', 31 => '7-3',
            ],
            '002750' => [
                1 => 'OFF', 2 => '7-3', 3 => 'OFF', 4 => '8-5', 5 => '8-5', 6 => '7-3', 7 => 'VL', 8 => '3-11', 9 => '3-11',
                10 => '11-7', 11 => '11-7', 12 => 'OFF', 13 => 'OFF', 14 => 'CTO', 15 => 'CTO', 16 => 'OFF',
                17 => 'OFF', 18 => '3-11', 19 => '3-11', 20 => '11-7', 21 => '11-7', 22 => 'OFF', 23 => 'OFF',
                24 => 'OFF', 25 => '7-3', 26 => '7-3', 27 => '8-5', 28 => '3-11', 29 => '11-7', 30 => 'OFF', 31 => 'OFF',
            ],
            '002751' => [
                1 => 'OFF', 2 => 'OFF', 3 => 'OFF', 4 => '8-5', 5 => '8-5', 6 => '8-5', 7 => '8-5', 8 => '8-5', 9 => 'OFF',
                10 => 'OFF', 11 => '8-5', 12 => '8-5', 13 => '8-5', 14 => '8-5', 15 => '8-5', 16 => 'OFF',
                17 => 'OFF', 18 => '8-5', 19 => '8-5', 20 => 'SPL', 21 => '8-5', 22 => '8-5', 23 => 'OFF',
                24 => 'OFF', 25 => '8-5', 26 => '8-5', 27 => '8-5', 28 => '8-5', 29 => '8-5', 30 => 'OFF', 31 => 'OFF',
            ],
            '001808' => [
                1 => 'OFF', 2 => 'OFF', 3 => 'OFF', 4 => '8-5', 5 => '8-5', 6 => '8-5', 7 => '8-5', 8 => '8-5', 9 => 'OFF',
                10 => 'OFF', 11 => '8-5', 12 => 'CTO', 13 => '8-5', 14 => '8-5', 15 => '8-5', 16 => 'OFF',
                17 => 'OFF', 18 => '8-5', 19 => '8-5', 20 => '8-5', 21 => '8-5', 22 => '8-5', 23 => 'OFF',
                24 => 'OFF', 25 => '8-5', 26 => '8-5', 27 => '8-5', 28 => '8-5', 29 => '8-5', 30 => 'OFF', 31 => 'OFF',
            ],
        ];
    }
}
