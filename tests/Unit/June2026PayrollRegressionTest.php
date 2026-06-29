<?php

namespace Tests\Unit;

use App\Livewire\Payroll\PayrollGeneration;
use App\Models\Payroll\PayrollAdditional;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class June2026PayrollRegressionTest extends TestCase
{
    public function test_june_2026_validated_workbook_cases_match_generation_totals(): void
    {
        $this->useMemoryDatabases();
        $this->createHrisTables();
        $this->createPayrollTables();
        $this->seedValidatedWorkbookFixture();

        $component = new PayrollGeneration;
        $component->period = '2026-06';
        $component->workingDays = 22;
        $component->gsisDays = 30;
        $component->selectedDepartmentIds = [10];
        $component->selectedDivisionIds = [1];
        $component->selectedLeaveTypeIds = [1];
        $component->employeeTypeFilter = ['plantilla', 'part_time'];
        $component->appliedEmployeeFilterIds = ['000742', '001415', '002039', '009999'];
        $component->loanColumnGroups = ['Other' => ['other_loans' => 'Other Loans']];

        $component->deductionDayOverrides = [
            '001415' => 1.5,
        ];
        $component->leaveDeductionOverrides = [
            '002039' => [
                'subsistence_days' => 0,
                'pera_days' => 0,
                'laundry_days' => 0,
                'tev_days' => 3,
            ],
        ];

        $rows = $this->payrollRows($component)->keyBy('emp_id');

        $expected = [
            '000742' => [
                'case' => 'LWOP',
                'basic_salary' => 24664.55,
                'subsistence' => 1400.0,
                'laundry' => 136.36,
                'pera' => 1818.18,
                'tax' => 303.64,
                'fifteenth' => 12254.09,
                'thirtieth' => 12254.09,
            ],
            '001415' => [
                'case' => 'Unauthorized leave',
                'basic_salary' => 20894.16,
                'subsistence' => 1425.0,
                'laundry' => 139.77,
                'pera' => 1863.64,
                'tax' => 0.0,
                'fifteenth' => 10797.42,
                'thirtieth' => 10797.41,
            ],
            '002039' => [
                'case' => 'TEV',
                'basic_salary' => 24329.0,
                'subsistence' => 1350.0,
                'laundry' => 150.0,
                'pera' => 2000.0,
                'tax' => 269.73,
                'fifteenth' => 12255.72,
                'thirtieth' => 12255.72,
            ],
            '009999' => [
                'case' => 'Part-time basic pay',
                'basic_salary' => 12164.5,
                'subsistence' => 750.0,
                'laundry' => 75.0,
                'pera' => 1000.0,
                'tax' => 0.0,
                'fifteenth' => 6170.29,
                'thirtieth' => 6170.29,
            ],
        ];

        foreach ($expected as $empId => $columns) {
            $row = $rows->get($empId);

            $this->assertNotNull($row, "Missing generated row for {$empId}");
            $this->assertSame($columns['basic_salary'], $row['basic_salary'], "{$columns['case']} basic pay");
            $this->assertSame($columns['subsistence'], $this->compensation($row, 'Subsistence'), "{$columns['case']} subsistence");
            $this->assertSame($columns['laundry'], $this->compensation($row, 'Laundry'), "{$columns['case']} laundry");
            $this->assertSame($columns['pera'], $this->compensation($row, 'PERA'), "{$columns['case']} PERA");
            $this->assertSame($columns['tax'], $row['tax']['monthly_tax_due'], "{$columns['case']} tax");
            $this->assertSame($columns['fifteenth'], $row['fifteenth'], "{$columns['case']} fifteenth");
            $this->assertSame($columns['thirtieth'], $row['thirtieth'], "{$columns['case']} thirtieth");
        }
    }

    private function payrollRows(PayrollGeneration $component): Collection
    {
        $method = new ReflectionMethod(PayrollGeneration::class, 'payrollRows');
        $method->setAccessible(true);

        return $method->invoke(
            $component,
            PayrollAdditional::query()->where('is_active', true)->orderBy('sort_order')->get(),
            collect(),
        );
    }

    private function compensation(array $row, string $name): float
    {
        foreach ($row['compensations'] as $compensation) {
            if ($compensation['name'] === $name) {
                return $compensation['amount'];
            }
        }

        $this->fail("Missing compensation {$name} for {$row['emp_id']}");
    }

    private function useMemoryDatabases(): void
    {
        Config::set('database.default', 'mysql');

        foreach (['mysql', 'payroll'] as $connection) {
            Config::set("database.connections.{$connection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]);
            DB::purge($connection);
        }
    }

    private function createHrisTables(): void
    {
        Schema::connection('mysql')->create('tbl_division', function (Blueprint $table) {
            $table->integer('division_id')->primary();
            $table->string('division');
        });

        Schema::connection('mysql')->create('tbl_department', function (Blueprint $table) {
            $table->integer('department_id')->primary();
            $table->string('department');
            $table->integer('division_id');
        });

        Schema::connection('mysql')->create('tbl_position', function (Blueprint $table) {
            $table->integer('position_id')->primary();
            $table->string('position_title');
            $table->integer('salary_grade');
            $table->string('remarks')->nullable();
        });

        Schema::connection('mysql')->create('tbl_employee', function (Blueprint $table) {
            $table->string('emp_id')->primary();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('extension')->nullable();
            $table->string('suffix')->nullable();
            $table->integer('position_id');
            $table->integer('department_id');
            $table->integer('empstat_id')->default(1);
            $table->string('is_active', 1)->default('Y');
            $table->integer('step')->default(1);
            $table->date('date_hired')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('phic_no')->nullable();
            $table->string('gsis_no')->nullable();
            $table->string('pagibig_no')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('tbl_salary_grade', function (Blueprint $table) {
            $table->id();
            $table->integer('salary_grade');
            $table->integer('step_increment');
            $table->decimal('salary', 14, 2);
            $table->date('effectivity_date');
        });

        Schema::connection('mysql')->create('tbl_leave_type', function (Blueprint $table) {
            $table->integer('leave_type_id')->primary();
            $table->string('leave_name');
            $table->boolean('to_display')->default(true);
        });

        Schema::connection('mysql')->create('tbl_employee_leave', function (Blueprint $table) {
            $table->id('leave_id');
            $table->string('emp_id');
            $table->integer('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_wpay', 8, 3)->default(0);
            $table->decimal('days_wopay', 8, 3)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::connection('mysql')->create('tbl_leave_log', function (Blueprint $table) {
            $table->id('log_id');
            $table->unsignedBigInteger('leave_id');
            $table->string('emp_id');
            $table->integer('action');
            $table->timestamps();
        });
    }

    private function createPayrollTables(): void
    {
        Schema::connection('payroll')->create('payroll_additional', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_percentage')->default(false);
            $table->decimal('value', 14, 4)->default(0);
            $table->string('computation_type')->nullable();
            $table->text('formula')->nullable();
            $table->string('variable_name')->nullable();
            $table->boolean('include_in_net_pay')->default(true);
            $table->string('tax_treatment')->default('non_taxable');
            $table->decimal('annual_exempt_limit', 14, 2)->nullable();
            $table->decimal('supplemental_tax_rate', 8, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::connection('payroll')->create('payroll_deduction', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_percentage')->default(false);
            $table->decimal('value', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
        });

        Schema::connection('payroll')->create('payroll_adjustment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_dtr_labels', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id');
            $table->date('dtr_date');
            $table->string('label');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_dtr_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id');
            $table->date('dtr_date');
            $table->string('adjustment_type');
            $table->integer('minutes')->default(0);
            $table->text('remarks')->nullable();
            $table->string('encoded_by')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_dtr_label_options', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('counts_as_excused_workday')->default(false);
            $table->boolean('counts_as_mra_hours')->default(false);
            $table->boolean('counts_as_leave_with_pay')->default(false);
            $table->boolean('counts_as_leave_without_pay')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_mra_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('department_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('final');
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('remarks')->nullable();
        });

        Schema::connection('payroll')->create('payroll_leave_credit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mra_report_id');
            $table->string('emp_id');
            $table->string('employee_name');
            $table->string('leave_type')->nullable();
            $table->decimal('beginning_balance', 14, 4)->default(0);
            $table->decimal('adjustment_days', 14, 4)->default(0);
            $table->decimal('ending_balance', 14, 4)->default(0);
            $table->integer('undertime_tardy_minutes')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('payroll')->create('payroll_loan_entities', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_loan_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->string('code');
            $table->string('name');
            $table->string('review_group');
            $table->string('review_column_key');
            $table->string('review_column_label');
            $table->json('match_keywords')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_loan_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_entity');
            $table->date('billing_period')->nullable();
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('imported_by')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->string('status')->default('validated');
        });

        Schema::connection('payroll')->create('payroll_loan_import_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->integer('row_number');
            $table->string('entity');
            $table->date('due_month');
            $table->string('employee_id')->nullable();
            $table->string('matched_emp_id')->nullable();
            $table->string('employee_name');
            $table->string('loan_account_no');
            $table->string('loan_type')->nullable();
            $table->decimal('monthly_amortization', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->decimal('outstanding_balance', 14, 2)->nullable();
            $table->decimal('principal_due', 14, 2)->nullable();
            $table->decimal('interest_due', 14, 2)->nullable();
            $table->decimal('penalty_due', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->string('validation_status')->default('valid');
            $table->json('validation_errors')->nullable();
        });

        Schema::connection('payroll')->create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->integer('department_id')->nullable();
            $table->string('payroll_period');
            $table->string('payroll_type')->default('monthly');
            $table->timestamp('snapshot_created_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_batch_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_batch_id');
            $table->string('emp_id');
            $table->integer('department_id')->nullable();
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->decimal('fifteenth', 14, 2)->default(0);
            $table->decimal('thirtieth', 14, 2)->default(0);
            $table->json('snapshot_json');
            $table->timestamps();
        });
    }

    private function seedValidatedWorkbookFixture(): void
    {
        DB::connection('mysql')->table('tbl_division')->insert([
            'division_id' => 1,
            'division' => 'Medical',
        ]);
        DB::connection('mysql')->table('tbl_department')->insert([
            'department_id' => 10,
            'department' => 'Validated Payroll',
            'division_id' => 1,
        ]);

        foreach ([11 => 27131, 9 => 22423, 10 => 24329] as $grade => $salary) {
            DB::connection('mysql')->table('tbl_position')->insert([
                'position_id' => $grade,
                'position_title' => "Validated SG {$grade}",
                'salary_grade' => $grade,
            ]);
            DB::connection('mysql')->table('tbl_salary_grade')->insert([
                'salary_grade' => $grade,
                'step_increment' => 1,
                'salary' => $salary,
                'effectivity_date' => '2026-01-01',
            ]);
        }

        DB::connection('mysql')->table('tbl_leave_type')->insert([
            'leave_type_id' => 1,
            'leave_name' => 'Leave Without Pay',
        ]);

        DB::connection('mysql')->table('tbl_employee')->insert([
            [
                'emp_id' => '000742',
                'firstname' => 'Shaila Marie',
                'middlename' => 'O',
                'lastname' => 'Ayson',
                'position_id' => 11,
                'department_id' => 10,
                'empstat_id' => 1,
                'is_active' => 'Y',
                'step' => 1,
                'date_hired' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'emp_id' => '001415',
                'firstname' => 'Mailyne Jane',
                'middlename' => 'S',
                'lastname' => 'Cabuyadao',
                'position_id' => 9,
                'department_id' => 10,
                'empstat_id' => 1,
                'is_active' => 'Y',
                'step' => 1,
                'date_hired' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'emp_id' => '002039',
                'firstname' => 'Clifford',
                'middlename' => 'M',
                'lastname' => 'Mabuti',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => 1,
                'is_active' => 'Y',
                'step' => 1,
                'date_hired' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'emp_id' => '009999',
                'firstname' => 'Part',
                'middlename' => 'T',
                'lastname' => 'Time',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => 3,
                'is_active' => 'Y',
                'step' => 1,
                'date_hired' => '2020-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('mysql')->table('tbl_employee_leave')->insert([
            'emp_id' => '000742',
            'leave_type' => 1,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
            'days_wpay' => 0,
            'days_wopay' => 2,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('payroll')->table('payroll_additional')->insert([
            [
                'name' => 'Subsistence',
                'value' => 1500,
                'computation_type' => 'formula',
                'formula' => 'max(0, configured_value - (configured_value / 30) * subsistence_deduct_days)',
                'variable_name' => 'subsistence_allowance',
                'include_in_net_pay' => true,
                'tax_treatment' => 'non_taxable',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Laundry',
                'value' => 150,
                'computation_type' => 'formula',
                'formula' => 'max(0, configured_value - (configured_value / 22) * laundry_deduct_days)',
                'variable_name' => 'laundry_allowance',
                'include_in_net_pay' => true,
                'tax_treatment' => 'non_taxable',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'PERA',
                'value' => 2000,
                'computation_type' => 'formula',
                'formula' => 'max(0, configured_value - (configured_value / 22) * pera_deduct_days)',
                'variable_name' => 'pera',
                'include_in_net_pay' => true,
                'tax_treatment' => 'non_taxable',
                'sort_order' => 30,
                'is_active' => true,
            ],
        ]);

        $loanEntityId = DB::connection('payroll')->table('payroll_loan_entities')->insertGetId([
            'code' => 'OTHER',
            'name' => 'Other',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('payroll')->table('payroll_loan_types')->insert([
            'entity_id' => $loanEntityId,
            'code' => 'OTHER',
            'name' => 'Other Loans',
            'review_group' => 'Other',
            'review_column_key' => 'other_loans',
            'review_column_label' => 'Other Loans',
            'match_keywords' => json_encode([]),
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
