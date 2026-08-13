<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('employee_masterlist_imports')) {
            $schema->create('employee_masterlist_imports', function (Blueprint $table) {
                $table->id();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('file_hash', 64)->index();
                $table->string('sheet_name')->default('Masterlist');
                $table->string('status', 24)->default('preview')->index();
                $table->date('effective_date');
                $table->json('options')->nullable();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('new_rows')->default(0);
                $table->unsignedInteger('changed_rows')->default(0);
                $table->unsignedInteger('unchanged_rows')->default(0);
                $table->unsignedInteger('warning_rows')->default(0);
                $table->unsignedInteger('error_rows')->default(0);
                $table->unsignedInteger('applied_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->string('imported_by_emp_id', 32)->nullable()->index();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('employee_masterlist_import_rows')) {
            $schema->create('employee_masterlist_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('import_id')->constrained('employee_masterlist_imports')->cascadeOnDelete();
                $table->unsignedInteger('source_row');
                $table->string('emp_id', 32)->nullable()->index();
                $table->string('action', 24)->default('unchanged')->index();
                $table->string('status', 24)->default('pending')->index();
                $table->boolean('selected')->default(true)->index();
                $table->json('source_payload');
                $table->json('changes')->nullable();
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                $table->unsignedInteger('resolved_position_id')->nullable();
                $table->unsignedInteger('resolved_department_id')->nullable();
                $table->unsignedInteger('resolved_empstat_id')->nullable();
                $table->string('preview_employee_updated_at')->nullable();
                $table->string('row_hash', 64)->index();
                $table->text('failure_message')->nullable();
                $table->timestamps();

                $table->unique(['import_id', 'source_row']);
            });
        }

        if (! $schema->hasTable('employee_payroll_profiles')) {
            $schema->create('employee_payroll_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('emp_id', 32)->unique();
                $table->string('responsibility_center')->nullable();
                $table->text('mp2_account_1')->nullable();
                $table->text('mp2_account_2')->nullable();
                $table->text('mp2_account_3')->nullable();
                $table->text('mp2_account_4')->nullable();
                $table->text('lbp_account_no')->nullable();
                $table->string('batch_no', 64)->nullable();
                $table->unsignedSmallInteger('batch_year')->nullable();
                $table->string('fund_type', 128)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('employee_payroll_profiles');
        $schema->dropIfExists('employee_masterlist_import_rows');
        $schema->dropIfExists('employee_masterlist_imports');
    }
};
