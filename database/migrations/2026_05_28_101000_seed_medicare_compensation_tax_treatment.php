<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        DB::connection('payroll')->table('payroll_additional')->updateOrInsert(
            ['name' => 'Medicare'],
            [
                'is_percentage' => false,
                'value' => 0,
                'computation_type' => 'fixed',
                'formula' => null,
                'variable_name' => 'medicare',
                'sort_order' => 95,
                'is_active' => false,
                'include_in_net_pay' => true,
                'tax_treatment' => 'supplemental_flat_rate',
                'annual_exempt_limit' => null,
                'supplemental_tax_rate' => 0.15,
            ],
        );
    }

    public function down(): void
    {
        DB::connection('payroll')->table('payroll_additional')
            ->where('name', 'Medicare')
            ->where('variable_name', 'medicare')
            ->delete();
    }
};
