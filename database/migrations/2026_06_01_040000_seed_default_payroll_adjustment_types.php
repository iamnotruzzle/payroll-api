<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    private const DEFAULT_ADJUSTMENT_TYPES = [
        ['code' => 'BASIC_SALARY_ADJUSTMENT', 'name' => 'Basic Salary Adjustment', 'sort_order' => 10],
        ['code' => 'SUBSISTENCE_ADJUSTMENT', 'name' => 'Subsistence Adjustment', 'sort_order' => 20],
        ['code' => 'LAUNDRY_ADJUSTMENT', 'name' => 'Laundry Adjustment', 'sort_order' => 30],
        ['code' => 'PERA_ADJUSTMENT', 'name' => 'PERA Adjustment', 'sort_order' => 40],
    ];

    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_adjustment_types')) {
            return;
        }

        $now = now();

        foreach (self::DEFAULT_ADJUSTMENT_TYPES as $type) {
            DB::connection('payroll')->table('payroll_adjustment_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_adjustment_types')) {
            return;
        }

        DB::connection('payroll')->table('payroll_adjustment_types')
            ->whereIn('code', collect(self::DEFAULT_ADJUSTMENT_TYPES)->pluck('code')->all())
            ->delete();
    }
};
