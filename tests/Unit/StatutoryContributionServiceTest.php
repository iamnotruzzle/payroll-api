<?php

namespace Tests\Unit;

use App\Services\Payroll\StatutoryContributionService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StatutoryContributionServiceTest extends TestCase
{
    public function test_it_calculates_employee_and_government_shares_from_current_rules(): void
    {
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $result = app(StatutoryContributionService::class)->calculate(20000, '2026-05-01');

        $this->assertSame(1800.0, $result['employee']['life_retirement']);
        $this->assertSame(500.0, $result['employee']['phic']);
        $this->assertSame(200.0, $result['employee']['mandatory_pagibig']);
        $this->assertSame(2500.0, $result['employee_total']);

        $this->assertSame(2400.0, $result['employer']['government_life_retirement']);
        $this->assertSame(500.0, $result['employer']['government_phic']);
        $this->assertSame(200.0, $result['employer']['government_pagibig']);
        $this->assertSame(3100.0, $result['employer_total']);
    }

    public function test_it_does_not_apply_salary_floor_to_zero_salary(): void
    {
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $result = app(StatutoryContributionService::class)->calculate(0, '2026-05-01');

        $this->assertSame(0.0, $result['employee_total']);
        $this->assertSame(0.0, $result['employer_total']);
    }
}
