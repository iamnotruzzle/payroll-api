<?php

namespace Tests\Unit;

use App\Rules\Payroll\NoOverlappingStatutoryContributionBracket;
use App\Services\Payroll\StatutoryContributionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
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

    public function test_it_selects_matching_salary_bracket_when_database_rules_exist(): void
    {
        $this->usePayrollMemoryDatabase();
        $contributionId = $this->createContribution('pagibig', 'Pag-IBIG');

        DB::connection('payroll')->table('payroll_statutory_contribution_brackets')->insert([
            [
                'statutory_contribution_id' => $contributionId,
                'effective_start' => '2026-01-01',
                'effective_end' => null,
                'min_salary' => 0,
                'max_salary' => 1500,
                'employee_rate' => 0.01,
                'employer_rate' => 0.02,
                'employee_cap' => null,
                'employer_cap' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'statutory_contribution_id' => $contributionId,
                'effective_start' => '2026-01-01',
                'effective_end' => null,
                'min_salary' => 1500.01,
                'max_salary' => 10000,
                'employee_rate' => 0.02,
                'employer_rate' => 0.02,
                'employee_cap' => 200,
                'employer_cap' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $lowerSalary = app(StatutoryContributionService::class)->calculate(1000, '2026-05-01');
        $higherSalary = app(StatutoryContributionService::class)->calculate(2000, '2026-05-01');

        $this->assertSame(10.0, $lowerSalary['employee']['mandatory_pagibig']);
        $this->assertSame(20.0, $lowerSalary['employer']['government_pagibig']);
        $this->assertSame(40.0, $higherSalary['employee']['mandatory_pagibig']);
        $this->assertSame(40.0, $higherSalary['employer']['government_pagibig']);
    }

    public function test_it_prefers_an_actual_salary_match_before_falling_back_to_the_latest_effective_period(): void
    {
        $this->usePayrollMemoryDatabase();
        $contributionId = $this->createContribution('pagibig', 'Pag-IBIG');

        DB::connection('payroll')->table('payroll_statutory_contribution_brackets')->insert([
            [
                'statutory_contribution_id' => $contributionId,
                'effective_start' => '2026-01-01',
                'effective_end' => null,
                'min_salary' => 0,
                'max_salary' => 1500,
                'employee_rate' => 0.01,
                'employer_rate' => 0.01,
                'employee_cap' => null,
                'employer_cap' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'statutory_contribution_id' => $contributionId,
                'effective_start' => '2026-06-01',
                'effective_end' => null,
                'min_salary' => 1500.01,
                'max_salary' => 10000,
                'employee_rate' => 0.02,
                'employer_rate' => 0.02,
                'employee_cap' => 200,
                'employer_cap' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = app(StatutoryContributionService::class)->calculate(1000, '2026-07-01');

        $this->assertSame(10.0, $result['employee']['mandatory_pagibig']);
        $this->assertSame('2026-01-01', $result['details']['pagibig']['effective_start']);
    }

    public function test_statutory_contribution_bracket_validation_rejects_inclusive_salary_overlap(): void
    {
        $this->usePayrollMemoryDatabase();
        $contributionId = $this->createContribution('pagibig', 'Pag-IBIG');
        $this->insertBracket($contributionId, '2026-01-01', null, 0, 1500);

        $validator = Validator::make([
            'selectedContributionId' => $contributionId,
            'effectiveStart' => '2026-01-01',
            'effectiveEnd' => null,
            'minSalary' => 1500,
            'maxSalary' => 3000,
        ], [
            'minSalary' => [new NoOverlappingStatutoryContributionBracket],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('minSalary', $validator->errors()->messages());
    }

    public function test_statutory_contribution_bracket_validation_allows_adjacent_seeded_effective_dates(): void
    {
        $this->usePayrollMemoryDatabase();
        $contributionId = $this->createContribution('pagibig', 'Pag-IBIG');
        $this->insertBracket($contributionId, '2016-04-19', '2024-01-31', 0, 5000);

        $validator = Validator::make([
            'selectedContributionId' => $contributionId,
            'effectiveStart' => '2024-02-01',
            'effectiveEnd' => null,
            'minSalary' => 0,
            'maxSalary' => 10000,
        ], [
            'minSalary' => [new NoOverlappingStatutoryContributionBracket],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_statutory_contribution_bracket_validation_rejects_open_ended_overlap(): void
    {
        $this->usePayrollMemoryDatabase();
        $contributionId = $this->createContribution('philhealth', 'PhilHealth');
        $this->insertBracket($contributionId, '2025-01-01', null, 10000, null);

        $validator = Validator::make([
            'selectedContributionId' => $contributionId,
            'effectiveStart' => '2026-01-01',
            'effectiveEnd' => null,
            'minSalary' => 100000,
            'maxSalary' => null,
        ], [
            'minSalary' => [new NoOverlappingStatutoryContributionBracket],
        ]);

        $this->assertTrue($validator->fails());
    }

    private function usePayrollMemoryDatabase(): void
    {
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('payroll');

        Schema::connection('payroll')->create('payroll_statutory_contributions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('split_across_cuts')->default(false);
            $table->boolean('is_mpf')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_statutory_contribution_brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('statutory_contribution_id');
            $table->date('effective_start')->nullable();
            $table->date('effective_end')->nullable();
            $table->decimal('min_salary', 14, 2)->default(0);
            $table->decimal('max_salary', 14, 2)->nullable();
            $table->decimal('employee_rate', 8, 4)->default(0);
            $table->decimal('employer_rate', 8, 4)->default(0);
            $table->decimal('employee_cap', 14, 2)->nullable();
            $table->decimal('employer_cap', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    private function createContribution(string $code, string $name): int
    {
        return DB::connection('payroll')->table('payroll_statutory_contributions')->insertGetId([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'split_across_cuts' => false,
            'is_mpf' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertBracket(
        int $contributionId,
        ?string $effectiveStart,
        ?string $effectiveEnd,
        float $minSalary,
        ?float $maxSalary,
    ): void {
        DB::connection('payroll')->table('payroll_statutory_contribution_brackets')->insert([
            'statutory_contribution_id' => $contributionId,
            'effective_start' => $effectiveStart,
            'effective_end' => $effectiveEnd,
            'min_salary' => $minSalary,
            'max_salary' => $maxSalary,
            'employee_rate' => 0.01,
            'employer_rate' => 0.01,
            'employee_cap' => null,
            'employer_cap' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
