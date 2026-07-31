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

        if (! $schema->hasTable('payroll_processed_leave_dates')) {
            $schema->create('payroll_processed_leave_dates', function (Blueprint $table) {
                $table->id();
                // payroll_generates.id is a legacy signed INT, so the FK must match it.
                $table->integer('payroll_run_id');
                $table->foreignId('payroll_batch_id')->constrained('payroll_batches')->cascadeOnDelete();
                $table->string('emp_id', 80)->index();
                $table->unsignedBigInteger('leave_id');
                $table->date('leave_date');
                $table->string('processed_by', 80)->nullable();
                $table->timestamps();

                $table->foreign('payroll_run_id')->references('id')->on('payroll_generates')->cascadeOnDelete();
                $table->unique(['leave_id', 'leave_date'], 'payroll_processed_leave_date_unique');
                $table->index(['emp_id', 'leave_date']);
            });
        } else {
            // Repair the table left behind by MySQL after the failed FK ALTER.
            if ($this->columnType('payroll_processed_leave_dates', 'payroll_run_id') !== 'int') {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->integer('payroll_run_id')->change();
                });
            }

            if (! $this->constraintExists('payroll_processed_leave_dates_payroll_run_id_foreign')) {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->foreign('payroll_run_id')->references('id')->on('payroll_generates')->cascadeOnDelete();
                });
            }

            if (! $this->constraintExists('payroll_processed_leave_dates_payroll_batch_id_foreign')) {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches')->cascadeOnDelete();
                });
            }

            if (! $this->indexExists('payroll_processed_leave_dates', 'payroll_processed_leave_dates_emp_id_index')) {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->index('emp_id');
                });
            }

            if (! $this->indexExists('payroll_processed_leave_dates', 'payroll_processed_leave_date_unique')) {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->unique(['leave_id', 'leave_date'], 'payroll_processed_leave_date_unique');
                });
            }

            if (! $this->indexExists('payroll_processed_leave_dates', 'payroll_processed_leave_dates_emp_id_leave_date_index')) {
                $schema->table('payroll_processed_leave_dates', function (Blueprint $table) {
                    $table->index(['emp_id', 'leave_date']);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_processed_leave_dates');
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
