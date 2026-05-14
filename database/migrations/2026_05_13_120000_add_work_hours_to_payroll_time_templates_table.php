<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_time_templates', function (Blueprint $table) {
            $table->decimal('work_hours', 6, 2)->default(0)->after('end_day_offset');
        });

        DB::connection('payroll')
            ->table('payroll_time_templates')
            ->orderBy('id')
            ->get(['id', 'start_time', 'end_time', 'end_day_offset'])
            ->each(function ($template) {
                $start = strtotime('2000-01-01 '.$template->start_time);
                $endDayOffset = (int) ($template->end_day_offset ?? 0);
                $end = strtotime('2000-01-01 '.$template->end_time.' +'.$endDayOffset.' day');

                if ($endDayOffset === 0 && $end <= $start) {
                    $end = strtotime('2000-01-02 '.$template->end_time);
                }

                $hours = max(0, round(($end - $start) / 3600, 2));

                DB::connection('payroll')
                    ->table('payroll_time_templates')
                    ->where('id', $template->id)
                    ->update(['work_hours' => $hours]);
            });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_time_templates', function (Blueprint $table) {
            $table->dropColumn('work_hours');
        });
    }
};
