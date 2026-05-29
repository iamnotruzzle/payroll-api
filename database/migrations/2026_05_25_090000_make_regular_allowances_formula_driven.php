<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    private const SUBSISTENCE_FORMULA = 'max(0, configured_value - 50 * subsistence_deduct_days) * (1 - (0.5 * is_part_time))';

    private const LAUNDRY_FORMULA = 'max(0, configured_value - 6.818 * laundry_deduct_days) * (1 - (0.5 * is_part_time))';

    public function up(): void
    {
        if (! $this->formulaColumnsExist()) {
            return;
        }

        $this->convertFixedRuleToFormula('subsistence_allowance', '%Subsistence%', self::SUBSISTENCE_FORMULA);
        $this->convertFixedRuleToFormula('laundry_allowance', '%Laundry%', self::LAUNDRY_FORMULA);
    }

    public function down(): void
    {
        if (! $this->formulaColumnsExist()) {
            return;
        }

        $this->restoreGeneratedFormulaToFixed(self::SUBSISTENCE_FORMULA);
        $this->restoreGeneratedFormulaToFixed(self::LAUNDRY_FORMULA);
    }

    private function convertFixedRuleToFormula(string $variableName, string $namePattern, string $formula): void
    {
        DB::connection('payroll')->table('payroll_additional')
            ->where(function ($query) use ($variableName, $namePattern) {
                $query->where('variable_name', $variableName)
                    ->orWhere('name', 'like', $namePattern);
            })
            ->where(function ($query) {
                $query->whereNull('computation_type')
                    ->orWhere('computation_type', 'fixed');
            })
            ->update([
                'computation_type' => 'formula',
                'formula' => $formula,
            ]);
    }

    private function restoreGeneratedFormulaToFixed(string $formula): void
    {
        DB::connection('payroll')->table('payroll_additional')
            ->where('computation_type', 'formula')
            ->where('formula', $formula)
            ->update([
                'computation_type' => 'fixed',
                'formula' => null,
            ]);
    }

    private function formulaColumnsExist(): bool
    {
        return Schema::connection('payroll')->hasTable('payroll_additional')
            && Schema::connection('payroll')->hasColumn('payroll_additional', 'computation_type')
            && Schema::connection('payroll')->hasColumn('payroll_additional', 'formula');
    }
};
