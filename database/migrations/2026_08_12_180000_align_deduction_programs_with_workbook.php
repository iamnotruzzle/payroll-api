<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('payroll');
        if (! $schema->hasTable('payroll_deduction')) {
            return;
        }

        $schema->table('payroll_deduction', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('payroll_deduction', 'section')) {
                $table->string('section', 20)->default('other')->after('insert_after_column');
            }
            if (! $schema->hasColumn('payroll_deduction', 'impact_type')) {
                $table->string('impact_type', 30)->default('employee_deduction')->after('section');
            }
            if (! $schema->hasColumn('payroll_deduction', 'is_recurring')) {
                $table->boolean('is_recurring')->default(true)->after('impact_type');
            }
        });

        if ($schema->hasTable('payroll_deduction_program_members')) {
            $schema->table('payroll_deduction_program_members', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('payroll_deduction_program_members', 'amount')) {
                    $table->decimal('amount', 14, 2)->nullable()->after('emp_id');
                }
                if (! $schema->hasColumn('payroll_deduction_program_members', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('amount');
                }
            });
        }

        DB::connection('payroll')->table('payroll_deduction')->whereIn('name', ['EA Deduction', 'HDMF (PS) 2 MS'])
            ->update(['section' => 'mandatory', 'impact_type' => 'employee_deduction', 'is_recurring' => true]);
        DB::connection('payroll')->table('payroll_deduction')->where('name', 'EA Deduction')->update(['insert_after_column' => 'government_pagibig']);
        DB::connection('payroll')->table('payroll_deduction')->updateOrInsert(
            ['name' => 'HDMF (PS) 2 MS'],
            ['is_percentage' => false, 'value' => 0, 'is_active' => true, 'sort_order' => 0,
                'insert_after_column' => 'mandatory_pagibig', 'section' => 'mandatory',
                'impact_type' => 'employee_deduction', 'is_recurring' => true]
        );
        foreach (['Death Aid', 'Penalty BAC', 'Longevity 2009-2010', 'MMSU'] as $index => $name) {
            DB::connection('payroll')->table('payroll_deduction')->updateOrInsert(['name' => $name], [
                'is_percentage' => false, 'value' => 0, 'is_active' => true, 'sort_order' => $index,
                'insert_after_column' => null, 'section' => 'other',
                'impact_type' => 'employee_deduction', 'is_recurring' => true,
            ]);
        }

        // Preserve current HDMF PS 2 MS employee amounts as recurring assignments.
        if ($schema->hasTable('payroll_loan_import_items') && $schema->hasTable('payroll_deduction_program_members')) {
            $programId = DB::connection('payroll')->table('payroll_deduction')->where('name', 'HDMF (PS) 2 MS')->value('id');
            if ($programId) {
                $latest = DB::connection('payroll')->table('payroll_loan_import_items')
                    ->whereIn('loan_type', ['HDMF PS 2 MS', 'HDMF (PS) 2 MS'])->where('amount_due', '>', 0)
                    ->whereNotNull('matched_emp_id')->orderByDesc('id')->get()->unique('matched_emp_id');
                foreach ($latest as $item) {
                    DB::connection('payroll')->table('payroll_deduction_program_members')->updateOrInsert(
                        ['deduction_program_id' => $programId, 'emp_id' => $item->matched_emp_id],
                        ['amount' => $item->amount_due, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Historical payroll and migrated assignments are intentionally retained.
    }
};
