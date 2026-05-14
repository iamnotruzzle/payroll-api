<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('payroll_scheduler')
            ->table('employee_schedule_settings')
            ->update([
                'uses_regular_weekday_schedule' => true,
                'can_rotate_shift' => false,
                'default_shift_code_id' => null,
            ]);

        DB::connection('payroll_scheduler')->statement(
            'ALTER TABLE employee_schedule_settings MODIFY uses_regular_weekday_schedule TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    public function down(): void
    {
        DB::connection('payroll_scheduler')->statement(
            'ALTER TABLE employee_schedule_settings MODIFY uses_regular_weekday_schedule TINYINT(1) NOT NULL DEFAULT 0'
        );
    }
};
