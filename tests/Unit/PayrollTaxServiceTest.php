<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollTaxService;
use Tests\TestCase;

class PayrollTaxServiceTest extends TestCase
{
    public function test_monthly_withholding_tax_matches_actual_payroll_tax_table(): void
    {
        $service = app(PayrollTaxService::class);

        $monthlyTaxableIncome = 31705 + 1450 - (2853.45 + 792.62 + 200 + 50);

        $this->assertSame(1263.89, $service->monthlyWithholdingTaxDue($monthlyTaxableIncome));
        $this->assertSame(
            1363.89,
            round($service->monthlyWithholdingTaxDue($monthlyTaxableIncome) + 100, 2)
        );
    }

    public function test_annualization_matches_actual_payroll_for_abellon(): void
    {
        $result = app(PayrollTaxService::class)->annualization([
            'current_basic' => 31705,
            'current_hazard' => 7926.25,
            'current_subsistence' => 1450,
            'current_mandatory_deductions' => 3896.07,
            'previous_basic' => 158525,
            'previous_hazard' => 39631.25,
            'previous_subsistence' => 7150,
            'previous_mandatory_deductions' => 19480.35,
            'previous_tax_withheld' => 12647.20,
            'future_months' => 6,
            'leave_without_pay_months' => 0,
            'hazard_subsistence_deduction_months' => 0,
            'hazard_rate' => 0.25,
            'gross_withholding_tax_adjustment' => 100,
        ]);

        $this->assertSame(446422.13, $result['annual_taxable_income']);
        $this->assertSame(31784.43, $result['annual_tax_due']);
        $this->assertSame(2745.44, $result['current_tax_withheld']);
        $this->assertSame(15932.22, $result['future_tax_withheld']);
        $this->assertSame(-459.57, $result['under_over_withheld']);
        $this->assertSame(2655.37, $result['monthly_annualized_tax_due']);
    }

    public function test_future_tax_withheld_uses_unrounded_monthly_annualized_tax_due(): void
    {
        $result = app(PayrollTaxService::class)->annualization([
            'current_basic' => 22423,
            'current_hazard' => 5605.75,
            'current_subsistence' => 1500,
            'current_mandatory_deductions' => 2828.64,
            'previous_basic' => 112115,
            'previous_hazard' => 28028.75,
            'previous_subsistence' => 7100,
            'previous_mandatory_deductions' => 14143.20,
            'previous_tax_withheld' => 3920.35,
            'future_months' => 6,
            'leave_without_pay_months' => 0,
            'hazard_subsistence_deduction_months' => 0,
            'hazard_rate' => 0.25,
        ]);

        $this->assertSame(880.02, $result['monthly_annualized_tax_due']);
        $this->assertSame(5280.10, $result['future_tax_withheld']);
    }
}
