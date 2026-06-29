<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->string('employee_type', 255)->change();
        });

        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->string('employee_type', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->string('employee_type', 20)->change();
        });

        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->string('employee_type', 20)->nullable()->change();
        });
    }
};
