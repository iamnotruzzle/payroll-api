<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll_scheduler')->table('staffing_requirements', function (Blueprint $table) {
            if (! Schema::connection('payroll_scheduler')->hasColumn('staffing_requirements', 'rotation_group_id')) {
                $table->foreignId('rotation_group_id')->nullable()->after('department_id')->constrained('rotation_groups')->nullOnDelete();
            }
        });

        Schema::connection('payroll_scheduler')->table('schedule_templates', function (Blueprint $table) {
            if ($this->indexExists('schedule_templates', 'schedule_templates_name_unique')) {
                $table->dropUnique('schedule_templates_name_unique');
            }
        });

        Schema::connection('payroll_scheduler')->table('monthly_schedules', function (Blueprint $table) {
            if ($this->indexExists('monthly_schedules', 'monthly_schedules_department_id_year_month_unique')) {
                $table->dropUnique('monthly_schedules_department_id_year_month_unique');
            }

            if (! Schema::connection('payroll_scheduler')->hasColumn('monthly_schedules', 'schedule_template_id')) {
                $table->foreignId('schedule_template_id')->nullable()->after('department_id')->constrained('schedule_templates')->nullOnDelete();
            }

            if (! Schema::connection('payroll_scheduler')->hasColumn('monthly_schedules', 'rotation_group_id')) {
                $table->foreignId('rotation_group_id')->nullable()->after('schedule_template_id')->constrained('rotation_groups')->nullOnDelete();
            }

            if (! $this->indexExists('monthly_schedules', 'monthly_schedules_dept_group_year_month_unique')) {
                $table->unique(
                    ['department_id', 'rotation_group_id', 'year', 'month'],
                    'monthly_schedules_dept_group_year_month_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->table('monthly_schedules', function (Blueprint $table) {
            if ($this->indexExists('monthly_schedules', 'monthly_schedules_dept_group_year_month_unique')) {
                $table->dropUnique('monthly_schedules_dept_group_year_month_unique');
            }

            if (Schema::connection('payroll_scheduler')->hasColumn('monthly_schedules', 'rotation_group_id')) {
                $table->dropConstrainedForeignId('rotation_group_id');
            }

            if (Schema::connection('payroll_scheduler')->hasColumn('monthly_schedules', 'schedule_template_id')) {
                $table->dropConstrainedForeignId('schedule_template_id');
            }

            if (! $this->indexExists('monthly_schedules', 'monthly_schedules_department_id_year_month_unique')) {
                $table->unique(['department_id', 'year', 'month']);
            }
        });

        Schema::connection('payroll_scheduler')->table('schedule_templates', function (Blueprint $table) {
            if (! $this->indexExists('schedule_templates', 'schedule_templates_name_unique')) {
                $table->unique('name');
            }
        });

        Schema::connection('payroll_scheduler')->table('staffing_requirements', function (Blueprint $table) {
            if (Schema::connection('payroll_scheduler')->hasColumn('staffing_requirements', 'rotation_group_id')) {
                $table->dropConstrainedForeignId('rotation_group_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::connection('payroll_scheduler')->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
