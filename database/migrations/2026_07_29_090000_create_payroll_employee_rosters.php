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
        $schema = Schema::connection('payroll');

        if (! $schema->hasTable('payroll_deduction_program_members')) {
            $schema->create('payroll_deduction_program_members', function (Blueprint $table) {
                $table->id();
                // payroll_deduction.id is a legacy signed INT, so the FK must match it.
                $table->integer('deduction_program_id');
                $table->string('emp_id', 80);
                $table->string('employee_name')->nullable();
                $table->string('source', 30)->default('manual');
                $table->string('imported_by', 80)->nullable();
                $table->timestamps();
                $table->foreign('deduction_program_id')->references('id')->on('payroll_deduction')->cascadeOnDelete();
                $table->unique(['deduction_program_id', 'emp_id'], 'payroll_program_member_unique');
                $table->index('emp_id');
            });
        } else {
            // MySQL can leave the table behind when a later ALTER TABLE statement
            // fails. Repair that partial first attempt so the migration is rerunnable.
            if ($this->columnType('payroll_deduction_program_members', 'deduction_program_id') !== 'int') {
                $schema->table('payroll_deduction_program_members', function (Blueprint $table) {
                    $table->integer('deduction_program_id')->change();
                });
            }

            if (! $this->constraintExists('payroll_deduction_program_members_deduction_program_id_foreign')) {
                $schema->table('payroll_deduction_program_members', function (Blueprint $table) {
                    $table->foreign('deduction_program_id')->references('id')->on('payroll_deduction')->cascadeOnDelete();
                });
            }

            if (! $this->indexExists('payroll_deduction_program_members', 'payroll_program_member_unique')) {
                $schema->table('payroll_deduction_program_members', function (Blueprint $table) {
                    $table->unique(['deduction_program_id', 'emp_id'], 'payroll_program_member_unique');
                });
            }

            if (! $this->indexExists('payroll_deduction_program_members', 'payroll_deduction_program_members_emp_id_index')) {
                $schema->table('payroll_deduction_program_members', function (Blueprint $table) {
                    $table->index('emp_id');
                });
            }
        }

        if (! $schema->hasTable('payroll_external_employee_overrides')) {
            $schema->create('payroll_external_employee_overrides', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_external_employee_overrides');
        Schema::connection('payroll')->dropIfExists('payroll_deduction_program_members');
    }

    private function columnType(string $table, string $column): ?string
    {
        return DB::connection('payroll')
            ->table('information_schema.columns')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('data_type');
    }

    private function constraintExists(string $constraint): bool
    {
        return DB::connection('payroll')
            ->table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = database()')
            ->where('constraint_name', $constraint)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::connection('payroll')
            ->table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
