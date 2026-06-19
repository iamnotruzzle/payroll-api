<?php

namespace Tests\Feature;

use App\Livewire\Payroll\PayrollHistory;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
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
}
