<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('historical_payroll_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64)->index();
            $table->string('payroll_period', 7);
            $table->string('payroll_type_code')->default('general');
            $table->string('status')->default('staged')->index();
            $table->unsignedInteger('sheet_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('difference_count')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('historical_payroll_import_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('historical_payroll_import_id');
            $table->string('sheet_name');
            $table->unsignedSmallInteger('header_row');
            $table->boolean('included')->default(true);
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('difference_count')->default(0);
            $table->json('column_map')->nullable();
            $table->timestamps();
            $table->foreign('historical_payroll_import_id', 'hist_payroll_sheet_import_fk')->references('id')->on('historical_payroll_imports')->cascadeOnDelete();
        });

        Schema::connection('payroll')->create('historical_payroll_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('historical_payroll_import_sheet_id');
            $table->unsignedInteger('source_row');
            $table->string('source_employee_no')->nullable()->index();
            $table->string('source_employee_name')->nullable();
            $table->string('matched_emp_id')->nullable()->index();
            $table->string('match_status')->default('unmatched')->index();
            $table->string('comparison_status')->default('unavailable')->index();
            $table->json('workbook_values');
            $table->json('system_values')->nullable();
            $table->json('differences')->nullable();
            $table->json('source_values')->nullable();
            $table->timestamps();
            $table->unique(['historical_payroll_import_sheet_id', 'source_row'], 'historical_import_sheet_row_unique');
            $table->foreign('historical_payroll_import_sheet_id', 'hist_payroll_row_sheet_fk')->references('id')->on('historical_payroll_import_sheets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('historical_payroll_import_rows');
        Schema::connection('payroll')->dropIfExists('historical_payroll_import_sheets');
        Schema::connection('payroll')->dropIfExists('historical_payroll_imports');
    }
};
