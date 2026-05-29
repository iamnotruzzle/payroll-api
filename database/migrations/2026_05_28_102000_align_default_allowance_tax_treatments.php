<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('name', 'like', '%PERA%')
                    ->orWhere('name', 'like', '%personal economic relief%')
                    ->orWhere('name', 'like', '%laundry%');
            })
            ->update([
                'tax_treatment' => 'non_taxable',
                'annual_exempt_limit' => null,
                'supplemental_tax_rate' => null,
            ]);

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('name', 'like', '%subsistence%')
                    ->orWhere('variable_name', 'like', '%subsistence%');
            })
            ->update([
                'tax_treatment' => 'regular_taxable',
                'annual_exempt_limit' => null,
                'supplemental_tax_rate' => null,
            ]);
    }

    public function down(): void
    {
        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('name', 'like', '%PERA%')
                    ->orWhere('name', 'like', '%personal economic relief%')
                    ->orWhere('name', 'like', '%laundry%');
            })
            ->update([
                'tax_treatment' => 'regular_taxable',
            ]);
    }
};
