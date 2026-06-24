<?php

namespace Tests\Feature;

use App\Livewire\Payroll\PayrollHistory;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollGenerationDraft;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.default', 'mysql');

        DB::purge('mysql');
        DB::purge('payroll');

        Schema::connection('mysql')->create('tbl_leave_type', function (Blueprint $table) {
            $table->integer('leave_type_id')->primary();
            $table->string('leave_name');
            $table->text('description')->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->boolean('to_display')->default(true);
            $table->boolean('processable')->default(true);
        });

        Schema::connection('mysql')->create('tbl_division', function (Blueprint $table) {
            $table->integer('division_id')->primary();
            $table->string('division');
        });

        Schema::connection('mysql')->create('tbl_department', function (Blueprint $table) {
            $table->integer('department_id')->primary();
            $table->integer('division_id')->nullable();
            $table->string('department');
        });

        Schema::connection('payroll')->create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('payroll_period');
            $table->string('payroll_type')->default('monthly');
            $table->string('payroll_type_code', 50)->nullable();
            $table->unsignedTinyInteger('working_days')->nullable();
            $table->unsignedTinyInteger('gsis_days')->nullable();
            $table->json('included_leave_type_ids')->nullable();
            $table->string('employee_type', 20)->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamp('snapshot_created_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_batch_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_batch_id');
            $table->string('emp_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->decimal('fifteenth', 14, 2)->default(0);
            $table->decimal('thirtieth', 14, 2)->default(0);
            $table->json('snapshot_json');
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_generation_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('configuration_key', 64)->unique();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('payroll_type_code', 50);
            $table->string('payroll_period', 7);
            $table->unsignedTinyInteger('working_days');
            $table->unsignedTinyInteger('gsis_days')->default(30);
            $table->json('included_leave_type_ids')->nullable();
            $table->string('employee_type', 20);
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('state_json');
            $table->string('saved_by')->nullable();
            $table->timestamp('saved_at');
            $table->timestamps();
        });
    }

    public function test_history_output_shows_finalized_batch_generation_configuration(): void
    {
        DB::connection('mysql')->table('tbl_leave_type')->insert([
            ['leave_type_id' => 1, 'leave_name' => 'Vacation Leave'],
            ['leave_type_id' => 2, 'leave_name' => 'Sick Leave'],
        ]);

        $batch = PayrollBatch::query()->create([
            'division_id' => 10,
            'department_id' => 20,
            'payroll_period' => '2026-06',
            'payroll_type' => 'General Payroll',
            'payroll_type_code' => 'general',
            'working_days' => 22,
            'gsis_days' => 27,
            'included_leave_type_ids' => [2, 1],
            'employee_type' => 'plantilla',
            'generated_by' => 'Payroll User',
            'snapshot_created_at' => '2026-06-18 09:30:00',
        ]);

        PayrollBatchRecord::query()->create([
            'payroll_batch_id' => $batch->id,
            'emp_id' => 'E-001',
            'department_id' => 20,
            'gross' => 1000,
            'net' => 900,
            'fifteenth' => 450,
            'thirtieth' => 450,
            'snapshot_json' => [
                'column_groups' => [
                    ['label' => 'Employee Information', 'columns' => ['employee_name']],
                ],
                'columns' => [
                    'employee_name' => ['label' => 'Employee Name'],
                ],
                'employee' => [
                    'employee_name' => 'Juan Dela Cruz',
                ],
            ],
        ]);

        $component = app(PayrollHistory::class);
        $component->selectBatch($batch->id);

        $html = $component->render()->render();

        $this->assertStringContainsString('GSIS:', $html);
        $this->assertStringContainsString('27 days', $html);
        $this->assertStringContainsString('GSIS Days', $html);
        $this->assertStringContainsString('Vacation Leave', $html);
        $this->assertStringContainsString('Sick Leave', $html);
        $this->assertStringContainsString('Plantilla', $html);
    }

    public function test_history_output_shows_saved_drafts_and_continue_link_targets_generation(): void
    {
        DB::connection('mysql')->table('tbl_leave_type')->insert([
            ['leave_type_id' => 1, 'leave_name' => 'Vacation Leave'],
            ['leave_type_id' => 2, 'leave_name' => 'Sick Leave'],
        ]);
        DB::connection('mysql')->table('tbl_division')->insert([
            ['division_id' => 10, 'division' => 'Finance Division'],
        ]);
        DB::connection('mysql')->table('tbl_department')->insert([
            ['department_id' => 20, 'division_id' => 10, 'department' => 'Billing and Claims'],
        ]);

        $draft = PayrollGenerationDraft::query()->create([
            'configuration_key' => str_repeat('a', 64),
            'division_id' => 10,
            'department_id' => 20,
            'payroll_type_code' => 'general',
            'payroll_period' => '2026-06',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [1, 2],
            'employee_type' => 'plantilla',
            'current_step' => 7,
            'state_json' => [
                'selected_division_ids' => [10],
                'selected_department_ids' => [20],
            ],
            'saved_by' => 'Payroll User',
            'saved_at' => '2026-06-19 10:15:00',
        ]);

        $component = app(PayrollHistory::class);
        $component->showTab('drafts');

        $html = $component->render()->render();

        $this->assertStringContainsString('Saved Drafts', $html);
        $this->assertStringContainsString('Billing and Claims', $html);
        $this->assertStringContainsString('Step:', $html);
        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('Continue Draft', $html);

        $response = $component->continueDraft($draft->id);

        $this->assertStringContainsString('/payroll/generation?', $response->getTargetUrl());
        $this->assertStringContainsString('department_ids=20', $response->getTargetUrl());
        $this->assertStringContainsString('period=2026-06', $response->getTargetUrl());
        $this->assertStringContainsString('leave_type_ids=1%2C2', $response->getTargetUrl());
    }

    public function test_saved_draft_can_be_deleted_from_history(): void
    {
        $draft = PayrollGenerationDraft::query()->create([
            'configuration_key' => str_repeat('b', 64),
            'division_id' => 10,
            'department_id' => 20,
            'payroll_type_code' => 'general',
            'payroll_period' => '2026-06',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'plantilla',
            'current_step' => 3,
            'state_json' => [],
            'saved_by' => 'Payroll User',
            'saved_at' => '2026-06-19 10:15:00',
        ]);

        $component = app(PayrollHistory::class);
        $component->deleteDraft($draft->id);

        $this->assertDatabaseMissing('payroll_generation_drafts', [
            'id' => $draft->id,
        ], 'payroll');
    }

    public function test_history_filters_by_payroll_period_type_employee_type_and_search(): void
    {
        PayrollBatch::query()->create([
            'payroll_period' => '2026-06',
            'payroll_type' => 'General Payroll',
            'payroll_type_code' => 'general',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'plantilla',
            'generated_by' => 'Payroll User',
            'snapshot_created_at' => '2026-06-18 09:30:00',
        ]);

        PayrollBatch::query()->create([
            'payroll_period' => '2026-05',
            'payroll_type' => 'Hazard Payroll',
            'payroll_type_code' => 'hazard',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'cos',
            'generated_by' => 'Other User',
            'snapshot_created_at' => '2026-05-18 09:30:00',
        ]);

        PayrollGenerationDraft::query()->create([
            'configuration_key' => str_repeat('c', 64),
            'payroll_type_code' => 'general',
            'payroll_period' => '2026-06',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'plantilla',
            'current_step' => 3,
            'state_json' => [],
            'saved_by' => 'Payroll User',
            'saved_at' => '2026-06-19 10:15:00',
        ]);

        PayrollGenerationDraft::query()->create([
            'configuration_key' => str_repeat('d', 64),
            'payroll_type_code' => 'hazard',
            'payroll_period' => '2026-05',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'cos',
            'current_step' => 3,
            'state_json' => [],
            'saved_by' => 'Other User',
            'saved_at' => '2026-05-19 10:15:00',
        ]);

        $component = app(PayrollHistory::class);
        $component->period = '2026-06';
        $component->payrollTypeFilter = 'general';
        $component->employeeTypeFilter = 'plantilla';
        $component->search = 'Payroll User';

        $finalizedHtml = $component->render()->render();

        $this->assertStringContainsString('2026-06', $finalizedHtml);
        $this->assertStringContainsString('General Payroll', $finalizedHtml);
        $this->assertStringNotContainsString('2026-05', $finalizedHtml);
        $this->assertStringNotContainsString('Hazard Payroll', $finalizedHtml);

        $component->showTab('drafts');

        $draftHtml = $component->render()->render();

        $this->assertStringContainsString('2026-06', $draftHtml);
        $this->assertStringContainsString('Payroll User', $draftHtml);
        $this->assertStringNotContainsString('2026-05', $draftHtml);
        $this->assertStringNotContainsString('Other User', $draftHtml);
    }
}
