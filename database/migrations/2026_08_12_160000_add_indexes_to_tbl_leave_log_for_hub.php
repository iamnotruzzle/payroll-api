<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive indexes for leave-log lookups used by employee hub / leave lists.
 * Does not alter legacy columns or remove anything.
 */
return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('tbl_leave_log')) {
            return;
        }

        Schema::connection($this->connection)->table('tbl_leave_log', function (Blueprint $table) {
            $sm = Schema::connection($this->connection);
            $indexes = collect($sm->getIndexes('tbl_leave_log'))->pluck('name')->all();

            if (! in_array('tbl_leave_log_leave_id_action_index', $indexes, true)
                && ! in_array('leave_id', $indexes, true)) {
                // Composite covers EXISTS/whereIn(leave_id)+action filters.
                try {
                    $table->index(['leave_id', 'action'], 'tbl_leave_log_leave_id_action_index');
                } catch (\Throwable) {
                    // Index may already exist under another name.
                }
            }

            if (! in_array('tbl_leave_log_emp_id_log_id_index', $indexes, true)) {
                try {
                    $table->index(['emp_id', 'log_id'], 'tbl_leave_log_emp_id_log_id_index');
                } catch (\Throwable) {
                    // Index may already exist under another name.
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('tbl_leave_log')) {
            return;
        }

        Schema::connection($this->connection)->table('tbl_leave_log', function (Blueprint $table) {
            try {
                $table->dropIndex('tbl_leave_log_leave_id_action_index');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('tbl_leave_log_emp_id_log_id_index');
            } catch (\Throwable) {
            }
        });
    }
};
