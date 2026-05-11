<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        $connection = 'payroll_scheduler';
        Schema::connection($connection)->create('employee_references', function (Blueprint $table) {
            $table->id();
            $table->string('hris_employee_id')->unique();
            $table->string('timekeeping_employee_id')->nullable()->index();
            $table->string('payroll_employee_id')->unique();
            $table->string('display_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('shift_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedTinyInteger('end_day_offset')->default(0);
            $table->boolean('is_work_shift')->default(true);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_leave_code')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('employee_schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->foreignId('default_shift_code_id')->nullable()->constrained('shift_codes')->nullOnDelete();
            $table->boolean('can_rotate_shift')->default(false);
            $table->unsignedSmallInteger('max_consecutive_duty_days')->default(5);
            $table->unsignedSmallInteger('max_night_shifts_per_month')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('rotation_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('rotation_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotation_group_id')->constrained('rotation_groups')->cascadeOnDelete();
            $table->string('employee_id')->index();
            $table->unsignedSmallInteger('rotation_order')->default(0);
            $table->timestamps();
            $table->unique(['rotation_group_id', 'employee_id']);
        });

        Schema::connection($connection)->create('staffing_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->foreignId('shift_code_id')->constrained('shift_codes')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedSmallInteger('minimum_staff')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->foreignId('rotation_group_id')->nullable()->constrained('rotation_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->constrained('schedule_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_sequence');
            $table->foreignId('shift_code_id')->constrained('shift_codes')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['schedule_template_id', 'day_sequence']);
        });

        Schema::connection($connection)->create('monthly_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 30)->default('draft');
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'year', 'month']);
        });

        Schema::connection($connection)->create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_schedule_id')->constrained('monthly_schedules')->cascadeOnDelete();
            $table->string('employee_id')->index();
            $table->date('schedule_date');
            $table->foreignId('shift_code_id')->constrained('shift_codes')->restrictOnDelete();
            $table->string('source', 30)->default('generated');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['monthly_schedule_id', 'employee_id', 'schedule_date'],
                'sched_assign_monthly_emp_date_unique'
            );
        });

        Schema::connection($connection)->create('schedule_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action', 50);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamp('performed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $connection = 'payroll_scheduler';
        foreach ([
            'schedule_audit_logs',
            'schedule_assignments',
            'monthly_schedules',
            'schedule_template_days',
            'schedule_templates',
            'staffing_requirements',
            'rotation_group_members',
            'rotation_groups',
            'employee_schedule_settings',
            'shift_codes',
            'employee_references',
        ] as $table) {
            Schema::connection($connection)->dropIfExists($table);
        }
    }
};