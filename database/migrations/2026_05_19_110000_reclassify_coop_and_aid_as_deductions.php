<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_loan_entities')
            || ! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        $now = now();
        $entityIds = DB::connection('payroll')->table('payroll_loan_entities')->pluck('id', 'code');

        foreach (['UCPB', 'DBP', 'LBP', 'COCO'] as $entityCode) {
            if (! isset($entityIds[$entityCode])) {
                continue;
            }

            DB::connection('payroll')->table('payroll_loan_types')
                ->where('entity_id', $entityIds[$entityCode])
                ->update(['review_group' => 'Bank Loans', 'updated_at' => $now]);
        }

        if (isset($entityIds['OTHER'])) {
            DB::connection('payroll')->table('payroll_loan_types')
                ->where('entity_id', $entityIds['OTHER'])
                ->where('code', 'OTHER')
                ->update(['review_group' => 'Bank Loans', 'updated_at' => $now]);

            DB::connection('payroll')->table('payroll_loan_types')->updateOrInsert(
                ['entity_id' => $entityIds['OTHER'], 'code' => 'DEATH_AID'],
                [
                    'name' => 'Death Aid',
                    'review_group' => 'Other Deductions',
                    'review_column_key' => 'death_aid',
                    'review_column_label' => 'Death Aid',
                    'match_keywords' => json_encode(['DEATH AID']),
                    'sort_order' => 98,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::connection('payroll')->table('payroll_loan_types')->updateOrInsert(
                ['entity_id' => $entityIds['OTHER'], 'code' => 'EA_MONTHLY_DUES'],
                [
                    'name' => 'EA Monthly Dues',
                    'review_group' => 'Other Deductions',
                    'review_column_key' => 'ea_monthly_dues',
                    'review_column_label' => 'EA Monthly Dues',
                    'match_keywords' => json_encode(['EA MONTHLY DUES', 'EA DUES', 'MONTHLY DUES']),
                    'sort_order' => 99,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if (isset($entityIds['MMMHCOOP'])) {
            DB::connection('payroll')->table('payroll_loan_types')->updateOrInsert(
                ['entity_id' => $entityIds['MMMHCOOP'], 'code' => 'COOP'],
                [
                    'name' => 'MMMH Cooperative Deduction',
                    'review_group' => 'Other Deductions',
                    'review_column_key' => 'mmmh_coop',
                    'review_column_label' => 'MMMH Coop',
                    'match_keywords' => json_encode(['COOP', 'MMMH COOP']),
                    'sort_order' => 97,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        DB::connection('payroll')->table('payroll_loan_types')
            ->whereIn('review_column_key', ['mmmh_coop', 'death_aid', 'ea_monthly_dues'])
            ->update(['review_group' => 'Banks / Other', 'updated_at' => now()]);
    }
};
