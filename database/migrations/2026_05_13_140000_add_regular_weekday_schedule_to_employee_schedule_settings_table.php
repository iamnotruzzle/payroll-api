<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll_scheduler')->table('employee_schedule_settings', function (Blueprint $table) {
            $table->boolean('uses_regular_weekday_schedule')->default(false)->after('can_rotate_shift');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->table('employee_schedule_settings', function (Blueprint $table) {
            $table->dropColumn('uses_regular_weekday_schedule');
        });
    }
};
