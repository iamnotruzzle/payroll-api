<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_statutory_contributions')) {
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
        }

        if (! Schema::connection('payroll')->hasTable('payroll_statutory_contribution_brackets')) {
            Schema::connection('payroll')->create('payroll_statutory_contribution_brackets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('statutory_contribution_id');
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

        Schema::connection('payroll')->table('payroll_statutory_contribution_brackets', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'effective_start')) {
                $table->date('effective_start')->nullable()->after('statutory_contribution_id');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'effective_end')) {
                $table->date('effective_end')->nullable()->after('effective_start');
            }
        });

        $this->seedContributionRules();
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_statutory_contribution_brackets')) {
            return;
        }

        DB::connection('payroll')->table('payroll_statutory_contribution_brackets')
            ->whereIn('remarks', $this->seededRemarks())
            ->delete();

        DB::connection('payroll')->table('payroll_statutory_contributions')
            ->whereIn('code', ['gsis_life_retirement', 'philhealth', 'pagibig'])
            ->delete();
    }

    private function seedContributionRules(): void
    {
        $now = now();
        $contributions = [
            'gsis_life_retirement' => [
                'name' => 'GSIS Life and Retirement',
                'remarks' => 'Employee share 9%; government share 12%.',
            ],
            'philhealth' => [
                'name' => 'PhilHealth',
                'remarks' => 'Premium rate split equally between employee and government share.',
            ],
            'pagibig' => [
                'name' => 'Pag-IBIG',
                'remarks' => 'Monthly contribution based on the applicable maximum monthly fund salary.',
            ],
        ];

        foreach ($contributions as $code => $data) {
            DB::connection('payroll')->table('payroll_statutory_contributions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'is_active' => true,
                    'split_across_cuts' => false,
                    'is_mpf' => false,
                    'remarks' => $data['remarks'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $ids = DB::connection('payroll')->table('payroll_statutory_contributions')
            ->whereIn('code', array_keys($contributions))
            ->pluck('id', 'code');

        $rules = [
            [
                'code' => 'gsis_life_retirement',
                'effective_start' => '2016-04-19',
                'effective_end' => null,
                'min_salary' => 0,
                'max_salary' => 999999999.99,
                'employee_rate' => 0.09,
                'employer_rate' => 0.12,
                'employee_cap' => null,
                'employer_cap' => null,
                'remarks' => 'Procedure basis: employee share 9%, government share 12%.',
            ],
            [
                'code' => 'philhealth',
                'effective_start' => '2025-01-01',
                'effective_end' => null,
                'min_salary' => 10000,
                'max_salary' => 100000,
                'employee_rate' => 0.025,
                'employer_rate' => 0.025,
                'employee_cap' => 2500,
                'employer_cap' => 2500,
                'remarks' => 'Current 5% premium split equally; salary base floor 10000 and ceiling 100000.',
            ],
            [
                'code' => 'pagibig',
                'effective_start' => '2016-04-19',
                'effective_end' => '2024-01-31',
                'min_salary' => 0,
                'max_salary' => 5000,
                'employee_rate' => 0.02,
                'employer_rate' => 0.02,
                'employee_cap' => 100,
                'employer_cap' => 100,
                'remarks' => 'Procedure basis: 2% with 5000 maximum fund salary.',
            ],
            [
                'code' => 'pagibig',
                'effective_start' => '2024-02-01',
                'effective_end' => null,
                'min_salary' => 0,
                'max_salary' => 10000,
                'employee_rate' => 0.02,
                'employer_rate' => 0.02,
                'employee_cap' => 200,
                'employer_cap' => 200,
                'remarks' => 'Current basis: 2% with 10000 maximum fund salary.',
            ],
        ];

        foreach ($rules as $rule) {
            $contributionId = $ids[$rule['code']] ?? null;
            if (! $contributionId) {
                continue;
            }

            DB::connection('payroll')->table('payroll_statutory_contribution_brackets')->updateOrInsert(
                [
                    'statutory_contribution_id' => $contributionId,
                    'effective_start' => $rule['effective_start'],
                    'effective_end' => $rule['effective_end'],
                    'remarks' => $rule['remarks'],
                ],
                [
                    'min_salary' => $rule['min_salary'],
                    'max_salary' => $rule['max_salary'],
                    'employee_rate' => $rule['employee_rate'],
                    'employer_rate' => $rule['employer_rate'],
                    'employee_cap' => $rule['employee_cap'],
                    'employer_cap' => $rule['employer_cap'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function seededRemarks(): array
    {
        return [
            'Procedure basis: employee share 9%, government share 12%.',
            'Current 5% premium split equally; salary base floor 10000 and ceiling 100000.',
            'Procedure basis: 2% with 5000 maximum fund salary.',
            'Current basis: 2% with 10000 maximum fund salary.',
        ];
    }
};
