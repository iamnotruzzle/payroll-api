<?php

namespace App\Services\Payroll;

class PayrollTaxService
{
    public const ANNUALIZED_MONTHS = 12;

    public const MONTHLY_WITHHOLDING_TAX_ADJUSTMENT = 0.0;

    public const PROJECTED_MONTHLY_SUBSISTENCE = 1500.0;

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

    public function monthlyWithholdingTaxDue(float $monthlyTaxableIncome): float
    {
        if ($monthlyTaxableIncome <= 0) {
            return 0.0;
        }

        return round(match (true) {
            $monthlyTaxableIncome < 20833 => 0,
            $monthlyTaxableIncome < 33333 => ($monthlyTaxableIncome - 20833) * 0.15,
            $monthlyTaxableIncome < 66667 => 1875 + (($monthlyTaxableIncome - 33333) * 0.20),
            $monthlyTaxableIncome < 166667 => 8541.8 + (($monthlyTaxableIncome - 66667) * 0.25),
            $monthlyTaxableIncome < 666667 => 33541.8 + (($monthlyTaxableIncome - 166667) * 0.30),
            default => 183541.8 + (($monthlyTaxableIncome - 666667) * 0.35),
        }, 2);
    }

    public function monthlyAnnualizedTaxDue(float $monthlyTaxableIncome): float
    {
        return round($this->monthlyAnnualizedTaxDueRaw($monthlyTaxableIncome), 2);
    }

    public function monthlyAnnualizedTaxDueRaw(float $monthlyTaxableIncome): float
    {
        if ($monthlyTaxableIncome <= 0) {
            return 0.0;
        }

        return match (true) {
            $monthlyTaxableIncome <= 20833.33 => 0,
            $monthlyTaxableIncome <= 33333.33 => ($monthlyTaxableIncome - 20833.33) * 0.15,
            $monthlyTaxableIncome <= 66666.67 => 1875 + (($monthlyTaxableIncome - 33333.33) * 0.20),
            $monthlyTaxableIncome <= 166666.67 => 8541.67 + (($monthlyTaxableIncome - 66666.67) * 0.25),
            $monthlyTaxableIncome <= 666666.67 => 33541.67 + (($monthlyTaxableIncome - 166666.67) * 0.30),
            default => 183541.67 + (($monthlyTaxableIncome - 666666.67) * 0.35),
        };
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

    public function annualization(array $input): array
    {
        $currentBasic = round(max(0, (float) ($input['current_basic'] ?? 0)), 2);
        $currentHazard = round(max(0, (float) ($input['current_hazard'] ?? 0)), 2);
        $currentSubsistence = round(max(0, (float) ($input['current_subsistence'] ?? 0)), 2);
        $currentMandatoryDeductions = round(max(0, (float) ($input['current_mandatory_deductions'] ?? 0)), 2);
        $previousBasic = round(max(0, (float) ($input['previous_basic'] ?? 0)), 2);
        $previousHazard = round(max(0, (float) ($input['previous_hazard'] ?? 0)), 2);
        $previousSubsistence = round(max(0, (float) ($input['previous_subsistence'] ?? 0)), 2);
        $previousMandatoryDeductions = round(max(0, (float) ($input['previous_mandatory_deductions'] ?? 0)), 2);
        $previousTaxWithheld = round(max(0, (float) ($input['previous_tax_withheld'] ?? 0)), 2);
        $futureMonths = max(0, (float) ($input['future_months'] ?? 0));
        $leaveWithoutPayMonths = max(0, (float) ($input['leave_without_pay_months'] ?? 0));
        $hazardSubsistenceDeductionMonths = max(0, (float) ($input['hazard_subsistence_deduction_months'] ?? 0));
        $futureBasicMonths = max(0, $futureMonths - $leaveWithoutPayMonths);
        $futureHazardSubsistenceMonths = max(0, $futureMonths - $leaveWithoutPayMonths - $hazardSubsistenceDeductionMonths);
        $hazardRate = max(0, (float) ($input['hazard_rate'] ?? 0));
        $projectedMonthlyMandatoryDeductions = max(
            0,
            (float) ($input['projected_monthly_mandatory_deductions'] ?? (
                ($currentBasic * 0.09)
                + ($currentBasic * 0.025)
                + 200
                + 50
            ))
        );
        $projectedMonthlySubsistence = round(max(
            0,
            (float) ($input['projected_monthly_subsistence'] ?? self::PROJECTED_MONTHLY_SUBSISTENCE)
        ), 2);

        $futureBasic = round($currentBasic * $futureBasicMonths, 2);
        $futureHazard = round(($currentBasic * $hazardRate) * $futureHazardSubsistenceMonths, 2);
        $futureSubsistence = round($projectedMonthlySubsistence * $futureHazardSubsistenceMonths, 2);
        $futureMandatoryDeductions = round($projectedMonthlyMandatoryDeductions * $futureMonths, 2);

        $totalBasic = round($previousBasic + $currentBasic + $futureBasic, 2);
        $totalHazard = round($previousHazard + $currentHazard + $futureHazard, 2);
        $totalSubsistence = round($previousSubsistence + $currentSubsistence + $futureSubsistence, 2);
        $totalMandatoryDeductions = round($previousMandatoryDeductions + $currentMandatoryDeductions + $futureMandatoryDeductions, 2);
        $annualTaxableIncome = round($totalBasic + $totalHazard + $totalSubsistence - $totalMandatoryDeductions, 2);
        $annualTaxDue = $this->annualTaxDue($annualTaxableIncome);

        $currentBasicTaxableIncome = round($currentBasic + $currentSubsistence - $currentMandatoryDeductions, 2);
        $currentTaxableIncomeWithHazard = round($currentBasicTaxableIncome + $currentHazard, 2);
        $regularMonthlyTaxDue = $this->monthlyWithholdingTaxDue($currentBasicTaxableIncome);
        $currentTaxWithHazard = $this->monthlyWithholdingTaxDue($currentTaxableIncomeWithHazard);
        $currentHazardTaxDue = $annualTaxableIncome > 250000
            ? round(max(0, $currentTaxWithHazard - $regularMonthlyTaxDue), 2)
            : 0.0;
        $grossTaxAdjustment = $annualTaxableIncome > 250000
            ? round(max(0, (float) ($input['gross_withholding_tax_adjustment'] ?? self::MONTHLY_WITHHOLDING_TAX_ADJUSTMENT)), 2)
            : 0.0;
        $supplementalTaxDue = round(max(0, (float) ($input['supplemental_tax_due'] ?? 0)), 2);
        $withholdingTaxGross = $annualTaxableIncome > 250000
            ? round($regularMonthlyTaxDue + $grossTaxAdjustment + $supplementalTaxDue, 2)
            : 0.0;
        $withholdingTaxAdjustment = round((float) ($input['withholding_tax_adjustment'] ?? 0), 2);
        $salaryWithholdingTax = round($withholdingTaxGross + $withholdingTaxAdjustment, 2);

        $futureMonthlyTaxableIncome = round(
            $currentBasic
                + ($currentBasic * $hazardRate)
                + $projectedMonthlySubsistence
                - $projectedMonthlyMandatoryDeductions,
            2
        );
        $monthlyAnnualizedTaxDueRaw = $this->monthlyAnnualizedTaxDueRaw($futureMonthlyTaxableIncome);
        $monthlyAnnualizedTaxDue = round($monthlyAnnualizedTaxDueRaw, 2);
        $futureTaxWithheld = round($monthlyAnnualizedTaxDueRaw * $futureHazardSubsistenceMonths, 2);
        $currentTaxWithheld = round($salaryWithholdingTax + $currentHazardTaxDue, 2);
        $totalTaxWithheld = round($previousTaxWithheld + $currentTaxWithheld + $futureTaxWithheld, 2);
        $underOverWithheld = round($totalTaxWithheld - $annualTaxDue, 2);

        return [
            'previous_basic' => $previousBasic,
            'current_basic' => $currentBasic,
            'future_basic' => $futureBasic,
            'total_basic' => $totalBasic,
            'previous_hazard' => $previousHazard,
            'current_hazard' => $currentHazard,
            'future_hazard' => $futureHazard,
            'total_hazard' => $totalHazard,
            'previous_subsistence' => $previousSubsistence,
            'current_subsistence' => $currentSubsistence,
            'future_subsistence' => $futureSubsistence,
            'total_subsistence' => $totalSubsistence,
            'previous_mandatory_deductions' => $previousMandatoryDeductions,
            'current_mandatory_deductions' => $currentMandatoryDeductions,
            'future_mandatory_deductions' => $futureMandatoryDeductions,
            'total_mandatory_deductions' => $totalMandatoryDeductions,
            'monthly_withholding_taxable_income' => $currentBasicTaxableIncome,
            'monthly_taxable_income_with_hazard' => $currentTaxableIncomeWithHazard,
            'future_monthly_taxable_income' => $futureMonthlyTaxableIncome,
            'annual_gross_income' => round($totalBasic + $totalHazard + $totalSubsistence, 2),
            'annual_mandatory_deductions' => $totalMandatoryDeductions,
            'annual_taxable_income' => $annualTaxableIncome,
            'annual_tax_due' => $annualTaxDue,
            'regular_monthly_tax_due' => $regularMonthlyTaxDue,
            'gross_withholding_tax_adjustment' => $grossTaxAdjustment,
            'supplemental_tax_due' => $supplementalTaxDue,
            'withholding_tax_gross' => $withholdingTaxGross,
            'withholding_tax_adjustment' => $withholdingTaxAdjustment,
            'monthly_tax_due' => $salaryWithholdingTax,
            'current_hazard_tax_due' => $currentHazardTaxDue,
            'previous_tax_withheld' => $previousTaxWithheld,
            'current_tax_withheld' => $currentTaxWithheld,
            'future_tax_withheld' => $futureTaxWithheld,
            'total_tax_withheld' => $totalTaxWithheld,
            'under_over_withheld' => $underOverWithheld,
            'monthly_annualized_tax_due' => $monthlyAnnualizedTaxDue,
            'future_months' => round($futureMonths, 4),
            'annualization_leave_without_pay_months' => round($leaveWithoutPayMonths, 4),
            'hazard_subsistence_deduction_months' => round($hazardSubsistenceDeductionMonths, 4),
            'future_basic_months' => round($futureBasicMonths, 4),
            'future_hazard_subsistence_months' => round($futureHazardSubsistenceMonths, 4),
        ];
    }
}
