<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        if (Schema::connection('payroll')->hasTable('payroll_dtr_correction_approvers')) {
            $this->ensureCompositeIndex();

            return;
        }

        Schema::connection('payroll')->create('payroll_dtr_correction_approvers', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id');
            $table->unsignedBigInteger('department_id');
            $table->string('approver_emp_id');
            $table->string('configured_by_emp_id')->nullable();
            $table->timestamps();

            $table->unique('emp_id', 'dtr_corr_appr_emp_unique');
            $table->index('department_id', 'dtr_corr_appr_dept_idx');
            $table->index('approver_emp_id', 'dtr_corr_appr_approver_idx');
            $table->index('configured_by_emp_id', 'dtr_corr_appr_configured_by_idx');
            $table->index(['department_id', 'approver_emp_id'], 'dtr_corr_appr_dept_approver_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_dtr_correction_approvers');
    }

    private function ensureCompositeIndex(): void
    {
        $exists = collect(DB::connection('payroll')->select("SHOW INDEX FROM payroll_dtr_correction_approvers WHERE Key_name = 'dtr_corr_appr_dept_approver_idx'"))
            ->isNotEmpty();

        if (! $exists) {
            Schema::connection('payroll')->table('payroll_dtr_correction_approvers', function (Blueprint $table) {
                $table->index(['department_id', 'approver_emp_id'], 'dtr_corr_appr_dept_approver_idx');
            });
        }
    }
};
