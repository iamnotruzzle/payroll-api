<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        $schema = Schema::connection('payroll');

        $schema->create('payroll_system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();
        });
        $schema->create('payroll_mode_changes', function (Blueprint $table) {
            $table->id();
            $table->string('from_mode', 20);
            $table->string('to_mode', 20);
            $table->string('changed_by', 80)->nullable();
            $table->json('readiness_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        $schema->create('payroll_source_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('source', 30);
            $table->string('status', 30)->default('staged');
            $table->string('schema_version', 20)->default('1.0');
            $table->string('original_filename')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->date('effective_date')->nullable();
            $table->string('effective_period', 7)->nullable();
            $table->json('statistics')->nullable();
            $table->json('errors')->nullable();
            $table->json('payload')->nullable();
            $table->string('created_by', 80)->nullable();
            $table->string('activated_by', 80)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'checksum', 'effective_period'], 'payroll_source_batch_idempotency');
            $table->index(['status', 'kind']);
        });
        $schema->create('payroll_canonical_divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('payroll_canonical_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->unsignedBigInteger('external_id')->unique();
            $table->unsignedBigInteger('division_external_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('division_external_id');
        });
        $schema->create('payroll_canonical_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('title');
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->string('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('payroll_canonical_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->string('emp_id', 80)->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('extension')->nullable();
            $table->string('suffix')->nullable();
            $table->unsignedBigInteger('position_external_id')->nullable();
            $table->unsignedBigInteger('department_external_id')->nullable();
            $table->unsignedTinyInteger('step')->nullable();
            $table->unsignedTinyInteger('empstat_id')->default(1);
            $table->date('date_hired')->nullable();
            $table->date('separation_date')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('gsis_no')->nullable();
            $table->string('phic_no')->nullable();
            $table->string('pagibig_no')->nullable();
            $table->decimal('vacation_leave_credits', 12, 3)->default(0);
            $table->decimal('sick_leave_credits', 12, 3)->default(0);
            $table->boolean('is_external')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('responsibility_center')->nullable();
            $table->text('lbp_account_no')->nullable();
            $table->string('fund_type')->nullable();
            $table->timestamps();
            $table->index(['department_external_id', 'is_active']);
        });
        $schema->create('payroll_canonical_salary_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->unsignedTinyInteger('salary_grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('salary', 14, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['salary_grade', 'step', 'effective_from'], 'payroll_salary_rate_version');
        });
        $schema->create('payroll_canonical_leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('payroll_canonical_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->string('external_id')->nullable()->unique();
            $table->string('emp_id', 80);
            $table->unsignedBigInteger('leave_type_external_id');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->decimal('days_wpay', 10, 4)->default(0);
            $table->decimal('days_wopay', 10, 4)->default(0);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->index(['emp_id', 'start_date', 'end_date']);
        });
        $schema->create('payroll_canonical_timekeeping', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_batch_id');
            $table->string('period', 7);
            $table->string('emp_id', 80);
            $table->decimal('total_work_days', 10, 4)->default(0);
            $table->decimal('days_with_dtr', 10, 4)->default(0);
            $table->decimal('regular_hours', 10, 4)->default(0);
            $table->decimal('undertime_hours', 10, 4)->default(0);
            $table->decimal('tardy_hours', 10, 4)->default(0);
            $table->decimal('mra_hours', 10, 4)->default(0);
            $table->decimal('leave_days_with_pay', 10, 4)->default(0);
            $table->decimal('leave_days_without_pay', 10, 4)->default(0);
            $table->decimal('absent_days', 10, 4)->default(0);
            $table->timestamps();
            $table->unique(['period', 'emp_id']);
        });
        $schema->create('payroll_user_accounts', function (Blueprint $table) {
            $table->id('userid');
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->string('emp_id', 80)->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password');
            $table->rememberToken();
            $table->unsignedTinyInteger('login_attempt')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $schema->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        $schema->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        $schema->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
        });
        $schema->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });
        $schema->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });

        if ($schema->hasTable('payroll_generates')) {
            $schema->table('payroll_generates', function (Blueprint $table) {
                $table->string('operating_mode', 20)->nullable()->after('status');
                $table->json('source_batch_ids')->nullable()->after('operating_mode');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('payroll');
        if ($schema->hasTable('payroll_generates')) {
            $schema->table('payroll_generates', fn (Blueprint $table) => $table->dropColumn(['operating_mode', 'source_batch_ids']));
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'payroll_user_accounts', 'payroll_canonical_timekeeping', 'payroll_canonical_leaves', 'payroll_canonical_leave_types', 'payroll_canonical_salary_rates', 'payroll_canonical_employees', 'payroll_canonical_positions', 'payroll_canonical_departments', 'payroll_canonical_divisions', 'payroll_source_batches', 'payroll_mode_changes', 'payroll_system_settings'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
