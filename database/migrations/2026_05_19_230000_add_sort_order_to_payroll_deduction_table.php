<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_deduction')) {
            return;
        }

        Schema::connection('payroll')->table('payroll_deduction', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_deduction', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_deduction')) {
            return;
        }

        Schema::connection('payroll')->table('payroll_deduction', function (Blueprint $table) {
            if (Schema::connection('payroll')->hasColumn('payroll_deduction', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
