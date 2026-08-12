<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->table('schedule_assignments', function (Blueprint $table) {
            if (! Schema::connection('payroll_scheduler')->hasColumn('schedule_assignments', 'is_temporary_floater')) {
                $table->boolean('is_temporary_floater')->default(false)->after('unit_id');
            }
            if (! Schema::connection('payroll_scheduler')->hasColumn('schedule_assignments', 'legacy_emp_sched_id')) {
                $table->unsignedBigInteger('legacy_emp_sched_id')->nullable()->after('notes')->index();
            }
        });

        Schema::connection($connection)->create('schedule_floater_pool_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->string('emp_id')->index();
            $table->foreignId('unit_id')->nullable()->constrained('schedule_units')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'emp_id'], 'sched_floater_pool_dept_emp_unique');
        });

        Schema::connection($connection)->create('schedule_monthly_floaters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->foreignId('unit_id')->nullable()->constrained('schedule_units')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('emp_id')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['department_id', 'year', 'month', 'emp_id', 'unit_id'],
                'sched_monthly_floater_unique'
            );
        });

        Schema::connection($connection)->create('schedule_on_call_pool_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->foreignId('unit_id')->nullable()->constrained('schedule_units')->nullOnDelete();
            $table->boolean('is_second')->default(false);
            $table->string('emp_id')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['department_id', 'emp_id', 'is_second', 'unit_id'],
                'sched_on_call_pool_unique'
            );
        });

        Schema::connection($connection)->create('schedule_swaps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->string('requester_emp_id')->index();
            $table->string('responder_emp_id')->index();
            $table->foreignId('requester_assignment_id')->constrained('schedule_assignments')->cascadeOnDelete();
            $table->foreignId('responder_assignment_id')->constrained('schedule_assignments')->cascadeOnDelete();
            $table->date('schedule_date');
            $table->string('status', 20)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->string('requested_by')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('schedulev2_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key')->index();
            $table->boolean('dry_run')->default(true);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('emp_id')->nullable()->index();
            $table->unsignedInteger('limit')->nullable();
            $table->string('status', 30)->default('running');
            $table->json('stats')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('schedulev2_legacy_maps', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 80);
            $table->string('source_key', 80);
            $table->string('target_table', 80);
            $table->unsignedBigInteger('target_id');
            $table->string('emp_id')->nullable()->index();
            $table->string('checksum', 64)->nullable();
            $table->foreignId('sync_run_id')->nullable()->constrained('schedulev2_sync_runs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_table', 'source_key'], 'schedulev2_legacy_map_source_unique');
            $table->index(['target_table', 'target_id']);
        });
    }

    public function down(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->dropIfExists('schedulev2_legacy_maps');
        Schema::connection($connection)->dropIfExists('schedulev2_sync_runs');
        Schema::connection($connection)->dropIfExists('schedule_swaps');
        Schema::connection($connection)->dropIfExists('schedule_on_call_pool_members');
        Schema::connection($connection)->dropIfExists('schedule_monthly_floaters');
        Schema::connection($connection)->dropIfExists('schedule_floater_pool_members');

        Schema::connection($connection)->table('schedule_assignments', function (Blueprint $table) {
            if (Schema::connection('payroll_scheduler')->hasColumn('schedule_assignments', 'legacy_emp_sched_id')) {
                $table->dropColumn('legacy_emp_sched_id');
            }
            if (Schema::connection('payroll_scheduler')->hasColumn('schedule_assignments', 'is_temporary_floater')) {
                $table->dropColumn('is_temporary_floater');
            }
        });
    }
};
