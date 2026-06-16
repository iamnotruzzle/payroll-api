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
        Schema::connection('payroll')->table('payroll_statutory_contribution_brackets', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'employee_fixed_amount')) {
                $table->decimal('employee_fixed_amount', 14, 2)->nullable()->after('employer_rate');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'employer_fixed_amount')) {
                $table->decimal('employer_fixed_amount', 14, 2)->nullable()->after('employee_fixed_amount');
            }
        });

        $now = now();
        $contributions = [
            'gsis_life_retirement' => [
                'name' => 'GSIS',
                'remarks' => 'Employee share 9%; government share 12%.',
            ],
            'philhealth' => [
                'name' => 'PHIC',
                'remarks' => 'Premium rate split equally between employee and government share.',
            ],
            'pagibig' => [
                'name' => 'HDMF',
                'remarks' => 'Monthly contribution based on the applicable maximum monthly fund salary.',
            ],
            'ec' => [
                'name' => 'EC',
                'remarks' => 'Fixed government share.',
            ],
            'ea_deduction' => [
                'name' => 'EA Deduction',
                'remarks' => 'Fixed employee deduction.',
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

        $fixedRules = [
            [
                'code' => 'ec',
                'employee_fixed_amount' => null,
                'employer_fixed_amount' => 100,
                'remarks' => 'Fixed EC government share.',
            ],
            [
                'code' => 'ea_deduction',
                'employee_fixed_amount' => 50,
                'employer_fixed_amount' => null,
                'remarks' => 'Fixed EA employee deduction.',
            ],
        ];

        foreach ($fixedRules as $rule) {
            $contributionId = $ids[$rule['code']] ?? null;
            if (! $contributionId) {
                continue;
            }

            DB::connection('payroll')->table('payroll_statutory_contribution_brackets')->updateOrInsert(
                [
                    'statutory_contribution_id' => $contributionId,
                    'effective_start' => '2016-04-19',
                    'effective_end' => null,
                    'remarks' => $rule['remarks'],
                ],
                [
                    'min_salary' => 0,
                    'max_salary' => 999999999.99,
                    'employee_rate' => 0,
                    'employer_rate' => 0,
                    'employee_fixed_amount' => $rule['employee_fixed_amount'],
                    'employer_fixed_amount' => $rule['employer_fixed_amount'],
                    'employee_cap' => null,
                    'employer_cap' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::connection('payroll')->table('payroll_statutory_contribution_brackets')
            ->whereIn('remarks', ['Fixed EC government share.', 'Fixed EA employee deduction.'])
            ->delete();

        DB::connection('payroll')->table('payroll_statutory_contributions')
            ->whereIn('code', ['ec', 'ea_deduction'])
            ->delete();

        Schema::connection('payroll')->table('payroll_statutory_contribution_brackets', function (Blueprint $table) {
            if (Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'employee_fixed_amount')) {
                $table->dropColumn('employee_fixed_amount');
            }

            if (Schema::connection('payroll')->hasColumn('payroll_statutory_contribution_brackets', 'employer_fixed_amount')) {
                $table->dropColumn('employer_fixed_amount');
            }
        });
    }
};
