<?php

namespace Tests\Feature;

use App\Livewire\Payroll\PayrollConfiguration;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollGenerationDraft;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayrollConfigurationTest extends TestCase
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

        Schema::connection('mysql')->create('tbl_division', function (Blueprint $table) {
            $table->integer('division_id')->primary();
            $table->string('division');
        });

        Schema::connection('mysql')->create('tbl_department', function (Blueprint $table) {
            $table->integer('department_id')->primary();
            $table->integer('division_id')->nullable();
            $table->string('department');
        });

        Schema::connection('mysql')->create('tbl_leave_type', function (Blueprint $table) {
            $table->integer('leave_type_id')->primary();
            $table->string('leave_name');
        });

        Schema::connection('payroll')->create('payroll_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('payroll')->create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('payroll_period');
            $table->string('payroll_type')->default('General');
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

        DB::connection('mysql')->table('tbl_division')->insert([
            ['division_id' => 10, 'division' => 'Finance Division'],
        ]);
        DB::connection('mysql')->table('tbl_department')->insert([
            ['department_id' => 20, 'division_id' => 10, 'department' => 'Billing and Claims'],
        ]);
        DB::connection('payroll')->table('payroll_types')->insert([
            'code' => 'general',
            'name' => 'General',
            'description' => 'General monthly salary payroll.',
            'sort_order' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_department_generation_is_flagged_when_parent_division_is_finalized_for_same_period_and_type(): void
    {
        PayrollBatch::query()->create([
            'division_id' => 10,
            'department_id' => null,
            'payroll_period' => '2026-06',
            'payroll_type' => 'General',
            'payroll_type_code' => 'general',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'plantilla',
            'generated_by' => 'Payroll User',
            'snapshot_created_at' => '2026-06-20 08:00:00',
        ]);

        $component = $this->configuredComponent([10], [20]);
        $component->proceed();

        $this->assertTrue($component->showExistingGenerationNotice);
        $this->assertSame('finalized', $component->existingGenerations[0]['type']);
        $this->assertStringContainsString('division', $component->existingGenerations[0]['description']);
    }

    public function test_division_generation_is_flagged_when_department_draft_exists_for_same_period_and_type(): void
    {
        PayrollGenerationDraft::query()->create([
            'configuration_key' => str_repeat('c', 64),
            'division_id' => 10,
            'department_id' => 20,
            'payroll_type_code' => 'general',
            'payroll_period' => '2026-06',
            'working_days' => 22,
            'gsis_days' => 30,
            'included_leave_type_ids' => [],
            'employee_type' => 'plantilla',
            'current_step' => 4,
            'state_json' => [
                'selected_division_ids' => [10],
                'selected_department_ids' => [20],
            ],
            'saved_by' => 'Payroll User',
            'saved_at' => '2026-06-20 08:00:00',
        ]);

        $component = $this->configuredComponent([10], []);
        $component->proceed();

        $this->assertTrue($component->showExistingGenerationNotice);
        $this->assertSame('draft', $component->existingGenerations[0]['type']);
        $this->assertStringContainsString('overlapping department/office or division', $component->existingGenerations[0]['description']);
    }

    private function configuredComponent(array $divisionIds, array $departmentIds): PayrollConfiguration
    {
        $component = app(PayrollConfiguration::class);
        $component->selectedDivisionIds = $divisionIds;
        $component->selectedDepartmentIds = $departmentIds;
        $component->payrollType = 'general';
        $component->period = '2026-06';
        $component->workingDays = 22;
        $component->gsisDays = 30;
        $component->selectedLeaveTypeIds = [];
        $component->employeeTypeFilter = ['plantilla'];

        return $component;
    }
}
