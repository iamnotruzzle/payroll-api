<?php

namespace Tests\Unit;

use App\Models\Hris\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeTypeScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('mysql');

        Schema::connection('mysql')->create('tbl_division', function (Blueprint $table) {
            $table->integer('division_id')->primary();
            $table->string('division');
        });

        Schema::connection('mysql')->create('tbl_department', function (Blueprint $table) {
            $table->integer('department_id')->primary();
            $table->string('department');
            $table->integer('division_id');
        });

        Schema::connection('mysql')->create('tbl_employee', function (Blueprint $table) {
            $table->string('emp_id')->primary();
            $table->string('firstname');
            $table->string('lastname');
            $table->integer('position_id');
            $table->integer('department_id');
            $table->integer('empstat_id');
            $table->string('is_active', 1)->default('Y');
        });

        DB::connection('mysql')->table('tbl_division')->insert([
            [
                'division_id' => 1,
                'division' => 'Medical Service',
            ],
            [
                'division_id' => 2,
                'division' => 'External',
            ],
        ]);

        DB::connection('mysql')->table('tbl_department')->insert([
            [
                'department_id' => 10,
                'department' => 'Regular Department',
                'division_id' => 1,
            ],
            [
                'department_id' => 20,
                'department' => 'External Department',
                'division_id' => 2,
            ],
        ]);

        DB::connection('mysql')->table('tbl_employee')->insert([
            [
                'emp_id' => '000001',
                'firstname' => 'Permanent',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_PERMANENT,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000002',
                'firstname' => 'Casual',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_CASUAL,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000003',
                'firstname' => 'Part Time',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_PART_TIME,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000004',
                'firstname' => 'Contractual',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_CONTRACTUAL,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000005',
                'firstname' => 'Temporary',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_TEMPORARY,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000006',
                'firstname' => 'Visiting',
                'lastname' => 'Consultant',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_VISITING_CONSULTANT,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000007',
                'firstname' => 'CoS',
                'lastname' => 'Employee',
                'position_id' => Employee::CONTRACT_OF_SERVICE_POSITION_ID,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_CONTRACT_OF_SERVICE,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000008',
                'firstname' => 'Probationary',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_PROBATIONARY,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000009',
                'firstname' => 'Intern',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_INTERN,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000010',
                'firstname' => 'External Status',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 10,
                'empstat_id' => Employee::EMPSTAT_EXTERNAL,
                'is_active' => 'Y',
            ],
            [
                'emp_id' => '000011',
                'firstname' => 'External Division',
                'lastname' => 'Employee',
                'position_id' => 10,
                'department_id' => 20,
                'empstat_id' => Employee::EMPSTAT_PERMANENT,
                'is_active' => 'Y',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('mysql')->dropIfExists('tbl_employee');
        Schema::connection('mysql')->dropIfExists('tbl_department');
        Schema::connection('mysql')->dropIfExists('tbl_division');
        DB::purge('mysql');

        parent::tearDown();
    }

    public function test_plantilla_employee_type_uses_permanent_employment_status(): void
    {
        $employees = Employee::query()
            ->employeeType(Employee::EMPLOYEE_TYPE_PLANTILLA)
            ->orderBy('emp_id')
            ->pluck('emp_id')
            ->all();

        $this->assertSame(['000001'], $employees);
    }

    public function test_employee_type_options_use_employment_statuses(): void
    {
        $expected = [
            Employee::EMPLOYEE_TYPE_CASUAL => ['000002'],
            Employee::EMPLOYEE_TYPE_PART_TIME => ['000003'],
            Employee::EMPLOYEE_TYPE_CONTRACTUAL => ['000004'],
            Employee::EMPLOYEE_TYPE_TEMPORARY => ['000005'],
            Employee::EMPLOYEE_TYPE_VISITING_CONSULTANT => ['000006'],
            Employee::EMPLOYEE_TYPE_COS => ['000007'],
            Employee::EMPLOYEE_TYPE_PROBATIONARY => ['000008'],
            Employee::EMPLOYEE_TYPE_INTERN => ['000009'],
        ];

        foreach ($expected as $type => $employeeIds) {
            $this->assertSame($employeeIds, $this->employeeIdsForType($type), "Unexpected employees for {$type}");
        }
    }

    public function test_cos_employee_type_uses_contract_of_service_employment_status(): void
    {
        $this->assertSame(['000007'], $this->employeeIdsForType(Employee::EMPLOYEE_TYPE_COS));
    }

    public function test_external_employee_type_uses_division_instead_of_employment_status(): void
    {
        $this->assertSame(['000011'], $this->employeeIdsForType(Employee::EMPLOYEE_TYPE_EXTERNAL));
    }

    public function test_employee_type_scope_accepts_multiple_statuses(): void
    {
        $this->assertSame(
            ['000001', '000003', '000011'],
            $this->employeeIdsForType([
                Employee::EMPLOYEE_TYPE_PLANTILLA,
                Employee::EMPLOYEE_TYPE_PART_TIME,
                Employee::EMPLOYEE_TYPE_EXTERNAL,
            ])
        );
    }

    public function test_all_employee_type_keeps_all_employment_statuses(): void
    {
        $this->assertSame([
            '000001',
            '000002',
            '000003',
            '000004',
            '000005',
            '000006',
            '000007',
            '000008',
            '000009',
            '000010',
            '000011',
        ], $this->employeeIdsForType(Employee::EMPLOYEE_TYPE_ALL));
    }

    private function employeeIdsForType(string|array $type): array
    {
        return Employee::query()
            ->employeeType($type)
            ->orderBy('emp_id')
            ->pluck('emp_id')
            ->all();
    }
}
