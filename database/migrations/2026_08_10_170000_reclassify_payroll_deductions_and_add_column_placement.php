<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_deduction')
            || ! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        Schema::connection('payroll')->table('payroll_deduction', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_deduction', 'insert_after_column')) {
                $table->string('insert_after_column', 80)->nullable()->after('sort_order');
            }
        });

        Schema::connection('payroll')->table('payroll_loan_types', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_loan_types', 'insert_after_column')) {
                $table->string('insert_after_column', 80)->nullable()->after('review_column_label');
            }
        });

        if (Schema::connection('payroll')->hasTable('payroll_statutory_contributions')) {
            DB::connection('payroll')->table('payroll_statutory_contributions')
                ->where('code', 'ea_deduction')->update(['is_active' => false]);
        }

        DB::connection('payroll')->table('payroll_deduction')->updateOrInsert(
            ['name' => 'EA Deduction'],
            ['is_percentage' => false, 'value' => 50, 'sort_order' => 0, 'insert_after_column' => 'government_pagibig', 'is_active' => true]
        );

        $entityId = DB::connection('payroll')->table('payroll_loan_entities')
            ->where('code', 'ADDITIONAL_PREMIUM')->value('id');

        if ($entityId) {
            DB::connection('payroll')->table('payroll_loan_types')->updateOrInsert(
                ['entity_id' => $entityId, 'code' => 'HDMF_PS_2_MS'],
                [
                    'name' => 'HDMF (PS) 2 MS',
                    'review_group' => 'Additional Premiums',
                    'review_column_key' => 'hdmf_ps_2_ms',
                    'review_column_label' => 'HDMF (PS) 2 MS',
                    'insert_after_column' => 'government_pagibig',
                    'match_keywords' => json_encode(['HDMF PS 2 MS', 'HDMF (PS) 2 MS']),
                    'sort_order' => 0,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_deduction')
            || ! Schema::connection('payroll')->hasTable('payroll_loan_types')) {
            return;
        }

        DB::connection('payroll')->table('payroll_deduction')->where('name', 'EA Deduction')->delete();
        DB::connection('payroll')->table('payroll_loan_types')->where('code', 'HDMF_PS_2_MS')->delete();
        if (Schema::connection('payroll')->hasTable('payroll_statutory_contributions')) {
            DB::connection('payroll')->table('payroll_statutory_contributions')
                ->where('code', 'ea_deduction')->update(['is_active' => true]);
        }

        if (Schema::connection('payroll')->hasColumn('payroll_deduction', 'insert_after_column')) {
            Schema::connection('payroll')->table('payroll_deduction', fn (Blueprint $table) => $table->dropColumn('insert_after_column'));
        }
        if (Schema::connection('payroll')->hasColumn('payroll_loan_types', 'insert_after_column')) {
            Schema::connection('payroll')->table('payroll_loan_types', fn (Blueprint $table) => $table->dropColumn('insert_after_column'));
        }
    }
};
