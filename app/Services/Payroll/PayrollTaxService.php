<?php

namespace App\Services\Payroll;

class PayrollTaxService
{
    public const ANNUALIZED_MONTHS = 12;

    public function monthlyTaxDue(float $monthlyTaxableIncome, float $months = self::ANNUALIZED_MONTHS): float
    {
        if ($monthlyTaxableIncome <= 0 || $months <= 0) {
            return 0.0;
        }

        return round($this->annualTaxDue($monthlyTaxableIncome * $months) / $months, 2);
    }

    public function annualTaxDue(float $annualTaxableIncome): float
    {
        return round(match (true) {
            $annualTaxableIncome <= 250000 => 0,
            $annualTaxableIncome <= 400000 => ($annualTaxableIncome - 250000) * 0.15,
            $annualTaxableIncome <= 800000 => 22500 + (($annualTaxableIncome - 400000) * 0.20),
            $annualTaxableIncome <= 2000000 => 102500 + (($annualTaxableIncome - 800000) * 0.25),
            $annualTaxableIncome <= 8000000 => 402500 + (($annualTaxableIncome - 2000000) * 0.30),
            default => 2202500 + (($annualTaxableIncome - 8000000) * 0.35),
        }, 2);
    }

    public function calculation(float $monthlyGrossIncome, float $monthlyMandatoryDeductions, float $months = self::ANNUALIZED_MONTHS): array
    {
        $monthlyTaxableIncome = max(0, $monthlyGrossIncome - $monthlyMandatoryDeductions);
        $annualGrossIncome = round(max(0, $monthlyGrossIncome) * $months, 2);
        $annualMandatoryDeductions = round(max(0, $monthlyMandatoryDeductions) * $months, 2);
        $annualTaxableIncome = round($monthlyTaxableIncome * $months, 2);
        $annualTaxDue = $this->annualTaxDue($annualTaxableIncome);

        return [
            'months' => $months,
            'monthly_gross_income' => round(max(0, $monthlyGrossIncome), 2),
            'monthly_mandatory_deductions' => round(max(0, $monthlyMandatoryDeductions), 2),
            'monthly_taxable_income' => round($monthlyTaxableIncome, 2),
            'annual_gross_income' => $annualGrossIncome,
            'annual_mandatory_deductions' => $annualMandatoryDeductions,
            'annual_taxable_income' => $annualTaxableIncome,
            'annual_tax_due' => $annualTaxDue,
            'monthly_tax_due' => round($annualTaxDue / max(1, $months), 2),
        ];
    }
}
