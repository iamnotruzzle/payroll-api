<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    private const INDEX = 'tbl_employee_leave_payroll_lookup_index';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        if (! $schema->hasTable('tbl_employee_leave')) {
            return;
        }

        $indexes = collect($schema->getIndexes('tbl_employee_leave'))->pluck('name');
        if ($indexes->contains(self::INDEX)) {
            return;
        }

        $schema->table('tbl_employee_leave', function (Blueprint $table) {
            $table->index(
                ['emp_id', 'status', 'start_date', 'end_date'],
                self::INDEX
            );
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        if (! $schema->hasTable('tbl_employee_leave')) {
            return;
        }

        $indexes = collect($schema->getIndexes('tbl_employee_leave'))->pluck('name');
        if (! $indexes->contains(self::INDEX)) {
            return;
        }

        $schema->table('tbl_employee_leave', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
