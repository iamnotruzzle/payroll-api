<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll_scheduler')->table('shift_codes', function (Blueprint $table) {
            if (! Schema::connection('payroll_scheduler')->hasColumn('shift_codes', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('id')->index();
            }

            if ($this->indexExists('shift_codes', 'shift_codes_code_unique')) {
                $table->dropUnique('shift_codes_code_unique');
            }

            if (! $this->indexExists('shift_codes', 'shift_codes_department_code_unique')) {
                $table->unique(['department_id', 'code'], 'shift_codes_department_code_unique');
            }
        });

        Schema::connection('payroll_scheduler')->table('rotation_groups', function (Blueprint $table) {
            if (! Schema::connection('payroll_scheduler')->hasColumn('rotation_groups', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('id')->index();
            }

            if ($this->indexExists('rotation_groups', 'rotation_groups_name_unique')) {
                $table->dropUnique('rotation_groups_name_unique');
            }

            if (! $this->indexExists('rotation_groups', 'rotation_groups_department_name_unique')) {
                $table->unique(['department_id', 'name'], 'rotation_groups_department_name_unique');
            }
        });

        Schema::connection('payroll_scheduler')->table('staffing_requirements', function (Blueprint $table) {
            if (! Schema::connection('payroll_scheduler')->hasColumn('staffing_requirements', 'maximum_staff')) {
                $table->unsignedSmallInteger('maximum_staff')->nullable()->after('minimum_staff');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->table('staffing_requirements', function (Blueprint $table) {
            if (Schema::connection('payroll_scheduler')->hasColumn('staffing_requirements', 'maximum_staff')) {
                $table->dropColumn('maximum_staff');
            }
        });

        Schema::connection('payroll_scheduler')->table('rotation_groups', function (Blueprint $table) {
            if ($this->indexExists('rotation_groups', 'rotation_groups_department_name_unique')) {
                $table->dropUnique('rotation_groups_department_name_unique');
            }

            if (Schema::connection('payroll_scheduler')->hasColumn('rotation_groups', 'department_id')) {
                $table->dropColumn('department_id');
            }

            if (! $this->indexExists('rotation_groups', 'rotation_groups_name_unique')) {
                $table->unique('name');
            }
        });

        Schema::connection('payroll_scheduler')->table('shift_codes', function (Blueprint $table) {
            if ($this->indexExists('shift_codes', 'shift_codes_department_code_unique')) {
                $table->dropUnique('shift_codes_department_code_unique');
            }

            if (Schema::connection('payroll_scheduler')->hasColumn('shift_codes', 'department_id')) {
                $table->dropColumn('department_id');
            }

            if (! $this->indexExists('shift_codes', 'shift_codes_code_unique')) {
                $table->unique('code');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::connection('payroll_scheduler')->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
