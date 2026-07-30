<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_deduction_program_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deduction_program_id')->constrained('payroll_deduction')->cascadeOnDelete();
            $table->string('emp_id', 80);
            $table->string('employee_name')->nullable();
            $table->string('source', 30)->default('manual');
            $table->string('imported_by', 80)->nullable();
            $table->timestamps();
            $table->unique(['deduction_program_id', 'emp_id'], 'payroll_program_member_unique');
            $table->index('emp_id');
        });

        Schema::connection('payroll')->create('payroll_external_employee_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id', 80)->unique();
            $table->string('employee_name')->nullable();
            $table->string('source', 30)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->string('imported_by', 80)->nullable();
            $table->timestamps();
            $table->index(['is_active', 'emp_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_external_employee_overrides');
        Schema::connection('payroll')->dropIfExists('payroll_deduction_program_members');
    }
};
