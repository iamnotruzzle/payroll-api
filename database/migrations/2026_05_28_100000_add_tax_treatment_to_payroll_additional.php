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
        Schema::connection('payroll')->table('payroll_additional', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'include_in_net_pay')) {
                $table->boolean('include_in_net_pay')->default(true)->after('variable_name');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'tax_treatment')) {
                $table->string('tax_treatment', 40)->default('regular_taxable')->after('include_in_net_pay');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'annual_exempt_limit')) {
                $table->decimal('annual_exempt_limit', 14, 2)->nullable()->after('tax_treatment');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_additional', 'supplemental_tax_rate')) {
                $table->decimal('supplemental_tax_rate', 8, 4)->nullable()->after('annual_exempt_limit');
            }
        });

        $now = now();

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('name', 'like', '%hazard%')
                    ->orWhere('variable_name', 'like', '%hazard%');
            })
            ->update([
                'include_in_net_pay' => false,
                'tax_treatment' => 'regular_taxable',
            ]);

        DB::connection('payroll')->table('payroll_additional')->updateOrInsert(
            ['name' => 'Hazard Pay'],
            [
                'is_percentage' => false,
                'value' => 0,
                'computation_type' => 'formula',
                'formula' => 'basic_salary * hazard_rate',
                'variable_name' => 'hazard_pay',
                'sort_order' => 90,
                'is_active' => false,
                'include_in_net_pay' => false,
                'tax_treatment' => 'regular_taxable',
            ],
        );

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('name', 'like', '%medicare%')
                    ->orWhere('variable_name', 'like', '%medicare%');
            })
            ->update([
                'tax_treatment' => 'supplemental_flat_rate',
                'supplemental_tax_rate' => 0.15,
            ]);
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_additional', function (Blueprint $table) {
            foreach (['supplemental_tax_rate', 'annual_exempt_limit', 'tax_treatment', 'include_in_net_pay'] as $column) {
                if (Schema::connection('payroll')->hasColumn('payroll_additional', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
