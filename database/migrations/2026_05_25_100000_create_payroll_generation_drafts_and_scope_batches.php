<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_generation_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('configuration_key', 64)->unique();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('payroll_type_code', 50);
            $table->string('payroll_period', 7);
            $table->unsignedTinyInteger('working_days');
            $table->string('employee_type', 20);
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('state_json');
            $table->string('saved_by')->nullable();
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->index(
                ['division_id', 'department_id', 'payroll_type_code', 'payroll_period'],
                'payroll_drafts_scope_idx'
            );
        });

        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('division_id')->nullable()->after('department_id');
            $table->string('payroll_type_code', 50)->nullable()->after('payroll_type');
            $table->unsignedTinyInteger('working_days')->nullable()->after('payroll_type_code');
            $table->string('employee_type', 20)->nullable()->after('working_days');

            $table->index(
                ['division_id', 'department_id', 'payroll_type_code', 'payroll_period'],
                'payroll_batches_generation_scope_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->dropIndex('payroll_batches_generation_scope_idx');
            $table->dropColumn(['division_id', 'payroll_type_code', 'working_days', 'employee_type']);
        });

        Schema::connection('payroll')->dropIfExists('payroll_generation_drafts');
    }
};
