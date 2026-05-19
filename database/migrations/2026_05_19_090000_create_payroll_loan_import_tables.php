<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_loan_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_entity', 80);
            $table->date('billing_period')->nullable();
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('imported_by', 80)->nullable();
            $table->timestamp('imported_at')->useCurrent();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->string('status', 30)->default('validated');
        });

        Schema::connection('payroll')->create('payroll_loan_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('payroll_loan_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('entity', 80);
            $table->date('due_month');
            $table->string('employee_id', 80)->nullable();
            $table->string('matched_emp_id', 80)->nullable()->index();
            $table->string('employee_name');
            $table->string('loan_account_no', 120);
            $table->string('loan_type', 120)->nullable();
            $table->decimal('monthly_amortization', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->decimal('outstanding_balance', 14, 2)->nullable();
            $table->decimal('principal_due', 14, 2)->nullable();
            $table->decimal('interest_due', 14, 2)->nullable();
            $table->decimal('penalty_due', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->string('validation_status', 20)->default('invalid')->index();
            $table->json('validation_errors')->nullable();

            $table->index(['due_month', 'validation_status']);
            $table->index(['entity', 'due_month']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_loan_import_items');
        Schema::connection('payroll')->dropIfExists('payroll_loan_imports');
    }
};
