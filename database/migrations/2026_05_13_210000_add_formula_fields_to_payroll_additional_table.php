<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_additional')) {
            return;
        }

        Schema::connection('payroll')->table('payroll_additional', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'computation_type')) {
                $table->string('computation_type', 20)->default('fixed')->after('name');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'formula')) {
                $table->text('formula')->nullable()->after('value');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'variable_name')) {
                $table->string('variable_name', 80)->nullable()->after('formula');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('variable_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_additional')) {
            return;
        }

        Schema::connection('payroll')->table('payroll_additional', function (Blueprint $table) {
            foreach (['sort_order', 'variable_name', 'formula', 'computation_type'] as $column) {
                if (Schema::connection('payroll')->hasColumn('payroll_additional', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
