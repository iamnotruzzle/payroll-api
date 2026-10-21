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

        Config::set('database.connections.hris', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.default', 'hris');

        DB::purge('hris');
        DB::purge('payroll');

        Schema::connection('hris')->create('tbl_division', function (Blueprint $table) {
            $table->integer('division_id')->primary();
            $table->string('division');
        });

        Schema::connection('hris')->create('tbl_department', function (Blueprint $table) {
            $table->integer('department_id')->primary();
            $table->integer('division_id')->nullable();
            $table->string('department');
        });

        Schema::connection('hris')->create('tbl_leave_type', function (Blueprint $table) {
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
        Schema::connection('payroll')->create('payroll_canonical_divisions', function (Blueprint $table) {
            $table->id();
            $table->integer('external_id')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::connection('payroll')->create('payroll_canonical_departments', function (Blueprint $table) {
            $table->id();
            $table->integer('external_id')->unique();
            $table->integer('division_external_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::connection('payroll')->create('payroll_canonical_leave_types', function (Blueprint $table) {
            $table->id();
            $table->integer('external_id')->unique();
            $table->string('name');
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

        DB::connection('hris')->table('tbl_division')->insert([
            ['division_id' => 10, 'division' => 'Finance Division'],
        ]);
        DB::connection('hris')->table('tbl_department')->insert([
            ['department_id' => 20, 'division_id' => 10, 'department' => 'Billing and Claims'],
        ]);
        DB::connection('payroll')->table('payroll_canonical_divisions')->insert(['external_id' => 10, 'name' => 'Finance Division', 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('payroll')->table('payroll_canonical_departments')->insert(['external_id' => 20, 'division_external_id' => 10, 'name' => 'Billing and Claims', 'created_at' => now(), 'updated_at' => now()]);
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

    public function test_api_preparation_uses_configuration_month_and_leave_cutoff(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        $user = \Mockery::mock();
        $user->emp_id = '001783';
        $user->shouldReceive('can')->with('payroll.system.sync')->andReturn(false);
        $user->shouldReceive('can')->with('payroll.generate')->andReturn(true);
        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
        $batch = new \App\Models\Payroll\PayrollSourceBatch;
        $batch->id = 123;
        $service = \Mockery::mock(\App\Services\Payroll\ApiCanonicalSyncService::class);
        $service->shouldReceive('sync')->once()->with('2026-06', '001783', '2026-05-01', '2026-05-31', 'employees', ['division_ids' => [10], 'department_ids' => [], 'employee_types' => ['plantilla']])->andReturn($batch);
        $component = $this->configuredComponent([10], []);
        $component->showExistingGenerationNotice = true;
        $component->syncApi($service);
        $this->assertFalse($component->showExistingGenerationNotice);
        $this->assertStringContainsString('batch #123', session('api_sync_status'));
        $this->assertSame(123, $component->apiPreparation['steps']['employees']['batch']);
    }

    public function test_api_preparation_rejects_connected_mode(): void
    {
        Config::set('payroll_standalone.forced_mode', 'connected');
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('payroll.system.sync')->andReturn(true);
        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
        $service = \Mockery::mock(\App\Services\Payroll\ApiCanonicalSyncService::class);
        $service->shouldNotReceive('sync');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->configuredComponent([], [])->syncApi($service);
    }

    public function test_payroll_user_can_sync_and_cannot_skip_preparation_steps(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        $user = \Mockery::mock();
        $user->shouldReceive('can')->with('payroll.system.sync')->andReturn(false);
        $user->shouldReceive('can')->with('payroll.generate')->andReturn(true);
        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
        $service = \Mockery::mock(\App\Services\Payroll\ApiCanonicalSyncService::class);
        $service->shouldNotReceive('sync');
        $component = $this->configuredComponent([10], []);
        $this->assertTrue($component->canSyncPayrollInputs());
        $component->syncApi($service, 'leaves');
        $this->assertTrue($component->getErrorBag()->has('sync'));
    }

    public function test_incomplete_preparation_blocks_generation(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        $component = $this->configuredComponent([10], []);
        $component->apiPreparation = ['context' => (new \ReflectionMethod($component, 'preparationContext'))->invoke($component), 'steps' => ['employees' => ['batch' => 1]]];
        $this->assertNull($component->proceed());
        $this->assertTrue($component->getErrorBag()->has('sync'));
    }

    public function test_fourth_step_uses_database_timekeeping_service_after_api_steps(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        $user = \Mockery::mock();
        $user->emp_id = '001783';
        $user->shouldReceive('can')->with('payroll.system.sync')->andReturn(false);
        $user->shouldReceive('can')->with('payroll.generate')->andReturn(true);
        \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($user);
        $batch = new \App\Models\Payroll\PayrollSourceBatch;
        $batch->id = 124;
        $database = \Mockery::mock(\App\Services\Payroll\HrisTimekeepingSyncService::class);
        $database->shouldReceive('sync')->once()->with('2026-06', '001783', ['division_ids' => [10], 'department_ids' => [], 'employee_types' => ['plantilla']])->andReturn($batch);
        $this->app->instance(\App\Services\Payroll\HrisTimekeepingSyncService::class, $database);
        $api = \Mockery::mock(\App\Services\Payroll\ApiCanonicalSyncService::class);
        $api->shouldNotReceive('sync');
        $component = $this->configuredComponent([10], []);
        $component->timekeepingSyncSource = 'hris_db';
        $component->apiPreparation = ['context' => (new \ReflectionMethod($component, 'preparationContext'))->invoke($component),
            'steps' => ['employees' => ['batch' => 1], 'salary' => ['batch' => 2], 'leaves' => ['batch' => 3]]];
        $component->syncApi($api, 'timekeeping');
        $this->assertSame(124, $component->apiPreparation['steps']['timekeeping']['batch']);
        $this->assertCount(4, $component->apiPreparation['steps']);
    }

    public function test_wizard_validates_run_details_before_advancing(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        $component = $this->configuredComponent([10], []);
        $component->workingDays = 0;
        try {
            $component->nextConfigurationStep();
            $this->fail('Invalid paid days must keep the user on Run details.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('workingDays', $e->errors());
        }
        $this->assertSame('details', $component->configurationStep);
        $component->workingDays = 22;
        $component->nextConfigurationStep();
        $this->assertSame('scope', $component->configurationStep);
        $component->nextConfigurationStep();
        $this->assertSame('preparation', $component->configurationStep);
        $component->nextConfigurationStep();
        $this->assertSame('review', $component->configurationStep);
        $component->previousConfigurationStep();
        $this->assertSame('preparation', $component->configurationStep);
    }

    public function test_connected_wizard_skips_preparation_and_renders_review(): void
    {
        Config::set('payroll_standalone.forced_mode', 'connected');
        $component = $this->configuredComponent([10], []);
        $component->nextConfigurationStep();
        $component->nextConfigurationStep();
        $this->assertSame('review', $component->configurationStep);
        \Livewire\Livewire::test(PayrollConfiguration::class)
            ->assertSee('Run details')->assertSee('Payroll scope')
            ->assertSee('Review before opening payroll generation')->assertDontSee('Prepare payroll data from HRIS')
            ->assertSee('Review and continue')->assertSee('Back to edit')
            ->assertSee('configuration-step-review', false)
            ->assertSee('x-on:submit.prevent="openStepReview()"', false);
    }

    public function test_departments_follow_selected_divisions_and_clear_invalid_selections(): void
    {
        DB::connection('payroll')->table('payroll_canonical_divisions')->insert(['external_id' => 11, 'name' => 'Other Division']);
        DB::connection('payroll')->table('payroll_canonical_departments')->insert(['external_id' => 21, 'division_external_id' => 11, 'name' => 'Other Department']);
        $component = $this->configuredComponent([10, 11], [20, 21]);
        $this->assertSame([20, 21], $component->render()->getData()['departments']->pluck('external_id')->sort()->values()->all());
        $component->selectedDivisionIds = [10];
        $component->updatedSelectedDivisionIds();
        $this->assertSame([20], $component->selectedDepartmentIds);
        $this->assertSame([20], $component->render()->getData()['departments']->pluck('external_id')->all());
        $component->selectedDivisionIds = [];
        $component->updatedSelectedDivisionIds();
        $this->assertSame([], $component->selectedDepartmentIds);
        $this->assertTrue($component->render()->getData()['departments']->isEmpty());
    }

    public function test_scope_rejects_department_outside_selected_divisions(): void
    {
        DB::connection('payroll')->table('payroll_canonical_departments')->insert(['external_id' => 21, 'division_external_id' => 11, 'name' => 'Other Department']);
        $component = $this->configuredComponent([10], [21]);
        $component->configurationStep = 'scope';
        try {
            $component->nextConfigurationStep();
            $this->fail('A department outside the selected divisions must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('selectedDepartmentIds.0', $e->errors());
        }
        $this->assertSame([10], $component->selectedDivisionIds);
    }

    public function test_guides_only_render_instructions_for_the_active_mode(): void
    {
        Config::set('payroll_standalone.forced_mode', 'standalone');
        \Livewire\Livewire::test(PayrollConfiguration::class)
            ->assertSee('Standalone mode')
            ->assertSee('Basic Pay discovers scoped IDs')
            ->assertDontSee('Click Synchronize connected sources');
        Config::set('payroll_standalone.forced_mode', 'connected');
        \Livewire\Livewire::test(PayrollConfiguration::class)
            ->assertSee('Connected mode')
            ->assertSee('Click Synchronize connected sources')
            ->assertDontSee('Basic Pay discovers scoped IDs')
            ->assertDontSee('Standalone mode');
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
        $component->leavePeriodStart = '2026-05-01';
        $component->leavePeriodEnd = '2026-05-31';
        $component->employeeTypeFilter = ['plantilla'];

        return $component;
    }
}
