<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll_scheduler')->table('shift_codes', function (Blueprint $table) {
            $table->decimal('work_hours', 6, 2)->nullable()->after('end_day_offset');
        });

        DB::connection('payroll_scheduler')
            ->table('shift_codes')
            ->orderBy('id')
            ->get(['id', 'start_time', 'end_time', 'end_day_offset'])
            ->each(function ($shiftCode): void {
                if (! $shiftCode->start_time || ! $shiftCode->end_time) {
                    return;
                }

                $start = strtotime('2000-01-01 '.$shiftCode->start_time);
                $endDayOffset = (int) ($shiftCode->end_day_offset ?? 0);
                $end = strtotime('2000-01-01 '.$shiftCode->end_time.' +'.$endDayOffset.' day');

                if ($endDayOffset === 0 && $end < $start) {
                    $end = strtotime('2000-01-02 '.$shiftCode->end_time);
                }

                DB::connection('payroll_scheduler')
                    ->table('shift_codes')
                    ->where('id', $shiftCode->id)
                    ->update(['work_hours' => round(max(0, ($end - $start) / 3600), 2)]);
            });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->table('shift_codes', function (Blueprint $table) {
            $table->dropColumn('work_hours');
        });
    }
};
