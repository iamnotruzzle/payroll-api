<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    private const OLD_SUBSISTENCE_FORMULA = 'max(0, configured_value - 50 * subsistence_deduct_days) * (1 - (0.5 * is_part_time))';

    private const OLD_LAUNDRY_FORMULA = 'max(0, configured_value - 6.818 * laundry_deduct_days) * (1 - (0.5 * is_part_time))';

    private const SUBSISTENCE_FORMULA = 'max(0, configured_value - (configured_value / 30) * subsistence_deduct_days) * (1 - (0.5 * is_part_time))';

    private const LAUNDRY_FORMULA = 'max(0, configured_value - (configured_value / 22) * laundry_deduct_days) * (1 - (0.5 * is_part_time))';

    private const PERA_FORMULA = 'max(0, configured_value - (configured_value / 22) * pera_deduct_days) * (1 - (0.5 * is_part_time))';

    public function up(): void
    {
        $this->updateAllowanceFormulas();
        $this->seedAdditionalPremiumReference();
    }

    public function down(): void
    {
        if ($this->hasPayrollAdditionalFormulaColumns()) {
            DB::connection('payroll')->table('payroll_additional')
                ->where('formula', self::SUBSISTENCE_FORMULA)
                ->update(['formula' => self::OLD_SUBSISTENCE_FORMULA]);

            DB::connection('payroll')->table('payroll_additional')
                ->where('formula', self::LAUNDRY_FORMULA)
                ->update(['formula' => self::OLD_LAUNDRY_FORMULA]);

            DB::connection('payroll')->table('payroll_additional')
                ->where('formula', self::PERA_FORMULA)
                ->update(['computation_type' => 'fixed', 'formula' => null]);
        }
    }

    private function updateAllowanceFormulas(): void
    {
        if (! $this->hasPayrollAdditionalFormulaColumns()) {
            return;
        }

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('variable_name', 'subsistence_allowance')
                    ->orWhere('name', 'like', '%Subsistence%')
                    ->orWhere('formula', self::OLD_SUBSISTENCE_FORMULA);
            })
            ->update([
                'computation_type' => 'formula',
                'formula' => self::SUBSISTENCE_FORMULA,
            ]);

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('variable_name', 'laundry_allowance')
                    ->orWhere('name', 'like', '%Laundry%')
                    ->orWhere('formula', self::OLD_LAUNDRY_FORMULA);
            })
            ->update([
                'computation_type' => 'formula',
                'formula' => self::LAUNDRY_FORMULA,
            ]);

        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) {
                $query->where('variable_name', 'pera')
                    ->orWhere('variable_name', 'like', '%pera%')
                    ->orWhere('name', 'like', '%PERA%')
                    ->orWhere('name', 'like', '%Personal Economic Relief%');
            })
            ->update([
                'computation_type' => 'formula',
                'formula' => self::PERA_FORMULA,
            ]);
    }

    private function seedAdditionalPremiumReference(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_loan_entities')
            || ! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        $now = now();
        $entityId = DB::connection('payroll')->table('payroll_loan_entities')
            ->where('code', 'ADDITIONAL_PREMIUM')
            ->value('id');

        if ($entityId) {
            DB::connection('payroll')->table('payroll_loan_entities')
                ->where('id', $entityId)
                ->update([
                    'name' => 'Additional Premiums',
                    'sort_order' => 900,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        } else {
            $entityId = DB::connection('payroll')->table('payroll_loan_entities')->insertGetId([
                'code' => 'ADDITIONAL_PREMIUM',
                'name' => 'Additional Premiums',
                'sort_order' => 900,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $typeId = DB::connection('payroll')->table('payroll_loan_types')
            ->where('entity_id', $entityId)
            ->where('code', 'ADDITIONAL_PREMIUM')
            ->value('id');

        $typePayload = [
                'name' => 'Additional Premium',
                'review_group' => 'Additional Premiums',
                'review_column_key' => 'additional_premium',
                'review_column_label' => 'Additional Premium',
                'match_keywords' => json_encode(['ADDITIONAL PREMIUM', 'PREMIUM', 'SAVINGS']),
                'sort_order' => 10,
                'is_active' => true,
                'updated_at' => $now,
            ];

        if ($typeId) {
            DB::connection('payroll')->table('payroll_loan_types')
                ->where('id', $typeId)
                ->update($typePayload);
        } else {
            DB::connection('payroll')->table('payroll_loan_types')->insert([
                'entity_id' => $entityId,
                'code' => 'ADDITIONAL_PREMIUM',
                ...$typePayload,
                'created_at' => $now,
            ]);
        }
    }

    private function hasPayrollAdditionalFormulaColumns(): bool
    {
        return Schema::connection('payroll')->hasTable('payroll_additional')
            && Schema::connection('payroll')->hasColumn('payroll_additional', 'computation_type')
            && Schema::connection('payroll')->hasColumn('payroll_additional', 'formula');
    }
};
