<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollStatutoryContribution;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class StatutoryContributionService
{
    private const FALLBACK_RULES = [
        'gsis_life_retirement' => [
            'employee_label' => 'life_retirement',
            'employer_label' => 'government_life_retirement',
            'name' => 'GSIS',
            'effective_start' => '2016-04-19',
            'effective_end' => null,
            'min_salary' => 0,
            'max_salary' => null,
            'employee_rate' => 0.09,
            'employer_rate' => 0.12,
            'employee_cap' => null,
            'employer_cap' => null,
        ],
        'philhealth' => [
            'employee_label' => 'phic',
            'employer_label' => 'government_phic',
            'name' => 'PHIC',
            'effective_start' => '2025-01-01',
            'effective_end' => null,
            'min_salary' => 10000,
            'max_salary' => 100000,
            'employee_rate' => 0.025,
            'employer_rate' => 0.025,
            'employee_cap' => 2500,
            'employer_cap' => 2500,
        ],
        'pagibig' => [
            'employee_label' => 'mandatory_pagibig',
            'employer_label' => 'government_pagibig',
            'name' => 'HDMF',
            'effective_start' => '2024-02-01',
            'effective_end' => null,
            'min_salary' => 0,
            'max_salary' => 10000,
            'employee_rate' => 0.02,
            'employer_rate' => 0.02,
            'employee_cap' => 200,
            'employer_cap' => 200,
        ],
        'ec' => [
            'employee_label' => null,
            'employer_label' => 'ec',
            'name' => 'EC',
            'effective_start' => '2016-04-19',
            'effective_end' => null,
            'min_salary' => 0,
            'max_salary' => null,
            'employee_rate' => 0,
            'employer_rate' => 0,
            'employee_fixed_amount' => null,
            'employer_fixed_amount' => 100,
            'employee_cap' => null,
            'employer_cap' => null,
        ],
        'ea_deduction' => [
            'employee_label' => 'ea_deduction',
            'employer_label' => null,
            'name' => 'EA Deduction',
            'effective_start' => '2016-04-19',
            'effective_end' => null,
            'min_salary' => 0,
            'max_salary' => null,
            'employee_rate' => 0,
            'employer_rate' => 0,
            'employee_fixed_amount' => 50,
            'employer_fixed_amount' => null,
            'employee_cap' => null,
            'employer_cap' => null,
        ],
    ];

    private const EMPLOYEE_LABELS = [
        'gsis_life_retirement' => 'life_retirement',
        'philhealth' => 'phic',
        'pagibig' => 'mandatory_pagibig',
        'ec' => null,
        'ea_deduction' => 'ea_deduction',
    ];

    private const EMPLOYER_LABELS = [
        'gsis_life_retirement' => 'government_life_retirement',
        'philhealth' => 'government_phic',
        'pagibig' => 'government_pagibig',
        'ec' => 'ec',
        'ea_deduction' => null,
    ];

    public function calculate(float $monthlySalary, CarbonInterface|string|null $effectiveDate = null): array
    {
        $date = $effectiveDate instanceof CarbonInterface
            ? $effectiveDate
            : Carbon::parse($effectiveDate ?: now());

        $rules = $this->rulesForDate($date, $monthlySalary);
        $employee = [
            'life_retirement' => 0.0,
            'phic' => 0.0,
            'mandatory_pagibig' => 0.0,
            'hdmf_ps_2_ms' => 0.0,
            'ea_deduction' => 0.0,
        ];
        $employer = [
            'government_life_retirement' => 0.0,
            'ec' => 0.0,
            'government_phic' => 0.0,
            'government_pagibig' => 0.0,
        ];
        $details = [];

        foreach ($rules as $code => $rule) {
            $employeeKey = array_key_exists($code, self::EMPLOYEE_LABELS)
                ? self::EMPLOYEE_LABELS[$code]
                : ($rule['employee_label'] ?? $code);
            $employerKey = array_key_exists($code, self::EMPLOYER_LABELS)
                ? self::EMPLOYER_LABELS[$code]
                : ($rule['employer_label'] ?? 'government_'.$code);
            $base = $this->contributionBase($monthlySalary, $rule);
            $employeeAmount = $this->contributionAmount($base, (float) $rule['employee_rate'], $rule['employee_cap'] ?? null, $rule['employee_fixed_amount'] ?? null);
            $employerAmount = $this->contributionAmount($base, (float) $rule['employer_rate'], $rule['employer_cap'] ?? null, $rule['employer_fixed_amount'] ?? null);

            if ($employeeKey !== null) {
                $employee[$employeeKey] = $employeeAmount;
            }

            if ($employerKey !== null) {
                $employer[$employerKey] = $employerAmount;
            }

            $details[$code] = [
                'name' => $rule['name'] ?? str($code)->replace('_', ' ')->title()->toString(),
                'base' => $base,
                'employee_amount' => $employeeAmount,
                'employer_amount' => $employerAmount,
                'employee_rate' => (float) $rule['employee_rate'],
                'employer_rate' => (float) $rule['employer_rate'],
                'employee_fixed_amount' => isset($rule['employee_fixed_amount']) ? (float) $rule['employee_fixed_amount'] : null,
                'employer_fixed_amount' => isset($rule['employer_fixed_amount']) ? (float) $rule['employer_fixed_amount'] : null,
                'employee_cap' => isset($rule['employee_cap']) ? (float) $rule['employee_cap'] : null,
                'employer_cap' => isset($rule['employer_cap']) ? (float) $rule['employer_cap'] : null,
                'effective_start' => $rule['effective_start'] ?? null,
                'effective_end' => $rule['effective_end'] ?? null,
            ];
        }

        return [
            'employee' => $employee,
            'employer' => $employer,
            'details' => $details,
            'employee_total' => round(array_sum($employee), 2),
            'employer_total' => round(array_sum($employer), 2),
        ];
    }

    private function rulesForDate(CarbonInterface $date, float $monthlySalary): array
    {
        try {
            $rules = PayrollStatutoryContribution::query()
                ->with(['brackets' => function ($query) use ($date) {
                    $query
                        ->where(function ($query) use ($date) {
                            $query->whereNull('effective_start')
                                ->orWhereDate('effective_start', '<=', $date->toDateString());
                        })
                        ->where(function ($query) use ($date) {
                            $query->whereNull('effective_end')
                                ->orWhereDate('effective_end', '>=', $date->toDateString());
                        })
                        ->orderByDesc('effective_start')
                        ->orderByDesc('min_salary');
                }])
                ->where('is_active', true)
                ->whereIn('code', array_keys(self::FALLBACK_RULES))
                ->get()
                ->mapWithKeys(function (PayrollStatutoryContribution $contribution) use ($monthlySalary) {
                    $bracket = $this->matchingBracket($contribution, $monthlySalary);
                    if (! $bracket) {
                        return [];
                    }

                    return [
                        $contribution->code => [
                            'name' => $contribution->name,
                            'effective_start' => $bracket->effective_start?->toDateString(),
                            'effective_end' => $bracket->effective_end?->toDateString(),
                            'min_salary' => (float) $bracket->min_salary,
                            'max_salary' => $bracket->max_salary !== null ? (float) $bracket->max_salary : null,
                            'employee_rate' => (float) $bracket->employee_rate,
                            'employer_rate' => (float) $bracket->employer_rate,
                            'employee_fixed_amount' => $bracket->employee_fixed_amount !== null ? (float) $bracket->employee_fixed_amount : null,
                            'employer_fixed_amount' => $bracket->employer_fixed_amount !== null ? (float) $bracket->employer_fixed_amount : null,
                            'employee_cap' => $bracket->employee_cap !== null ? (float) $bracket->employee_cap : null,
                            'employer_cap' => $bracket->employer_cap !== null ? (float) $bracket->employer_cap : null,
                        ],
                    ];
                });

            return $this->mergeWithFallbacks($rules);
        } catch (Throwable) {
            return self::FALLBACK_RULES;
        }
    }

    private function matchingBracket(PayrollStatutoryContribution $contribution, float $monthlySalary): mixed
    {
        $salary = max(0, $monthlySalary);
        $match = $contribution->brackets
            ->first(function ($bracket) use ($salary) {
                $min = (float) $bracket->min_salary;
                $max = $bracket->max_salary !== null ? (float) $bracket->max_salary : null;

                return $salary >= $min && ($max === null || $salary <= $max);
            });

        if ($match) {
            return $match;
        }

        $effectiveStart = $contribution->brackets->first()?->effective_start?->toDateString();
        $brackets = $contribution->brackets
            ->when($effectiveStart !== null, fn (Collection $brackets) => $brackets
                ->filter(fn ($bracket) => $bracket->effective_start?->toDateString() === $effectiveStart));

        $lowestBracket = $brackets->sortBy('min_salary')->first();
        if ($lowestBracket && $salary < (float) $lowestBracket->min_salary) {
            return $lowestBracket;
        }

        return $brackets->sortByDesc('min_salary')->first();
    }

    private function mergeWithFallbacks(Collection $rules): array
    {
        return collect(self::FALLBACK_RULES)
            ->map(fn (array $fallback, string $code) => array_merge($fallback, $rules->get($code, [])))
            ->all();
    }

    private function contributionBase(float $monthlySalary, array $rule): float
    {
        if ($monthlySalary <= 0) {
            return 0.0;
        }

        $base = max(0, $monthlySalary);
        $min = isset($rule['min_salary']) ? (float) $rule['min_salary'] : null;
        $max = isset($rule['max_salary']) ? (float) $rule['max_salary'] : null;

        if ($min !== null && $min > 0) {
            $base = max($base, $min);
        }

        if ($max !== null && $max > 0) {
            $base = min($base, $max);
        }

        return round($base, 2);
    }

    private function contributionAmount(float $base, float $rate, mixed $cap, mixed $fixedAmount = null): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        if ($fixedAmount !== null && $fixedAmount !== '') {
            return round(max(0, (float) $fixedAmount), 2);
        }

        $amount = round($base * $rate, 2);
        $cap = $cap !== null && $cap !== '' ? (float) $cap : null;

        if ($cap !== null && $cap > 0) {
            $amount = min($amount, $cap);
        }

        return round(max(0, $amount), 2);
    }
}
