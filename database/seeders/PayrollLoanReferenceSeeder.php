<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollLoanReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_loan_entities')
            || ! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        $now = now();

        DB::connection('payroll')->table('payroll_loan_types')
            ->whereIn('review_column_key', ['mmmh_coop', 'death_aid', 'ea_monthly_dues'])
            ->delete();

        DB::connection('payroll')->table('payroll_loan_entities')
            ->where('code', 'MMMHCOOP')
            ->delete();

        $entities = [
            ['code' => 'GSIS', 'name' => 'Government Service Insurance System', 'sort_order' => 10],
            ['code' => 'PAG-IBIG', 'name' => 'Pag-IBIG Fund', 'sort_order' => 20],
            ['code' => 'UCPB', 'name' => 'UCPB', 'sort_order' => 30],
            ['code' => 'DBP', 'name' => 'Development Bank of the Philippines', 'sort_order' => 40],
            ['code' => 'LBP', 'name' => 'Land Bank of the Philippines', 'sort_order' => 50],
            ['code' => 'COCO', 'name' => 'COCO Life', 'sort_order' => 60],
            ['code' => 'OTHER', 'name' => 'Other Loan Entity', 'sort_order' => 999],
        ];

        foreach ($entities as $entity) {
            DB::connection('payroll')->table('payroll_loan_entities')->updateOrInsert(
                ['code' => $entity['code']],
                $entity + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $entityIds = DB::connection('payroll')->table('payroll_loan_entities')->pluck('id', 'code');
        $types = [
            ['GSIS', 'EMERGENCY', 'Emergency Loan', 'GSIS', 'gsis_emergency', 'Emergency Loan', ['EMERGENCY', 'EL']],
            ['GSIS', 'COMPUTER', 'Computer Loan', 'GSIS', 'gsis_computer', 'Computer Loan', ['COMPUTER', 'CPL', 'DIGITAL']],
            ['GSIS', 'CONSO_MPL', 'Conso / MPL', 'GSIS', 'gsis_conso', 'Conso / MPL', ['CONSO', 'MPL']],
            ['GSIS', 'POLICY', 'Policy Loan', 'GSIS', 'gsis_policy', 'Policy Loan', ['POLICY']],
            ['GSIS', 'UOLI', 'UOLI Premium', 'GSIS', 'gsis_uoli', 'UOLI Prem.', ['UOLI']],
            ['GSIS', 'OPTIONAL_AJ', 'Optional / AJ', 'GSIS', 'gsis_optional', 'Optional / AJ', ['OPTIONAL', 'AJ']],
            ['PAG-IBIG', 'MPL', 'Multi-Purpose Loan', 'Pag-IBIG', 'pagibig_mpl', 'MPL', ['MPL']],
            ['PAG-IBIG', 'CALAMITY', 'Calamity Loan', 'Pag-IBIG', 'pagibig_calamity', 'Calamity', ['CAL', 'CALAMITY']],
            ['PAG-IBIG', 'MP2', 'Pag-IBIG II / MP2', 'Pag-IBIG', 'pagibig_mp2', 'MP2', ['MP2', 'PAG-IBIG II', 'PAGIBIG II']],
            ['UCPB', 'SALARY', 'UCPB Loan', 'Bank Loans', 'ucpb', 'UCPB', ['UCPB']],
            ['DBP', 'SALARY', 'DBP Loan', 'Bank Loans', 'dbp', 'DBP', ['DBP']],
            ['LBP', 'SALARY', 'LBP Loan', 'Bank Loans', 'lbp', 'LBP', ['LBP', 'LANDBANK', 'LAND BANK']],
            ['COCO', 'INSURANCE', 'COCO Life Insurance', 'Bank Loans', 'coco', 'COCO', ['COCO']],
            ['OTHER', 'OTHER', 'Other Loans', 'Bank Loans', 'other_loans', 'Other Loans', []],
        ];

        foreach ($types as $index => [$entityCode, $code, $name, $group, $key, $label, $keywords]) {
            DB::connection('payroll')->table('payroll_loan_types')->updateOrInsert(
                ['entity_id' => $entityIds[$entityCode], 'code' => $code],
                [
                    'name' => $name,
                    'review_group' => $group,
                    'review_column_key' => $key,
                    'review_column_label' => $label,
                    'match_keywords' => json_encode($keywords),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
