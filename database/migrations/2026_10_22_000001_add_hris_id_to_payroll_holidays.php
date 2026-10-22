<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_holidays', function (Blueprint $table) {
            $table->unsignedBigInteger('hris_holiday_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_holidays', function (Blueprint $table) {
            $table->dropUnique(['hris_holiday_id']);
            $table->dropColumn('hris_holiday_id');
        });
    }
};
