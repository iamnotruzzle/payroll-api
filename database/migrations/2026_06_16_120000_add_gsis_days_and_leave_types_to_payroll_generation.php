<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->unsignedTinyInteger('gsis_days')->default(30)->after('working_days');
            $table->json('included_leave_type_ids')->nullable()->after('gsis_days');
        });

        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->unsignedTinyInteger('gsis_days')->nullable()->after('working_days');
            $table->json('included_leave_type_ids')->nullable()->after('gsis_days');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->dropColumn(['gsis_days', 'included_leave_type_ids']);
        });

        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn(['gsis_days', 'included_leave_type_ids']);
        });
    }
};
