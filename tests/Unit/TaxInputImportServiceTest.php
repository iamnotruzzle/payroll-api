<?php

namespace Tests\Unit;

use App\Services\Payroll\TaxInputImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TaxInputImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.connections.hris', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('hris');
        Schema::connection('hris')->create('tbl_employee', function (Blueprint $table) {
            $table->string('emp_id')->primary();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('extension')->nullable();
            $table->string('suffix')->nullable();
            $table->string('is_active', 1)->default('Y');
        });
        DB::connection('hris')->table('tbl_employee')->insert([
            'emp_id' => '000742',
            'firstname' => 'Juan',
            'middlename' => 'Santos',
            'lastname' => 'Dela Cruz',
            'is_active' => 'Y',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('hris')->dropIfExists('tbl_employee');
        DB::purge('hris');
        parent::tearDown();
    }

    public function test_it_retains_only_the_six_supported_tax_inputs(): void
    {
        $values = (new TaxInputImportService)->retainedOverrides([
            'previous_basic' => 100,
            'previous_hazard' => 200,
            'previous_subsistence' => 300,
            'previous_mandatory_deductions' => 400,
            'previous_tax_withheld' => 500,
            'withholding_tax_adjustment' => -25,
            'future_months' => 6,
            'gross_withholding_tax_adjustment' => 99,
        ]);

        $this->assertSame([
            'previous_basic' => 100,
            'previous_hazard' => 200,
            'previous_subsistence' => 300,
            'previous_mandatory_deductions' => 400,
            'previous_tax_withheld' => 500,
            'withholding_tax_adjustment' => -25,
        ], $values);
    }

    public function test_it_previews_the_dedicated_template_and_validates_signed_fields(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Tax Inputs');
        $sheet->fromArray([[
            'Employee ID', 'Employee Name', 'Basic Previous', 'Hazard Previous',
            'Subsistence Previous', 'Mandatory Deduction Previous', 'Tax Withheld Previous', 'Tax Adjustment',
        ]], null, 'A1');
        $sheet->fromArray([[742, 'Dela Cruz Juan Santos', 1000, 200, 300, 400, 500, -25]], null, 'A2');
        $path = $this->saveWorkbook($book);

        try {
            $preview = (new TaxInputImportService)->preview($path);
            $this->assertTrue($preview[0]['valid']);
            $this->assertSame('000742', $preview[0]['emp_id']);
            $this->assertSame(-25.0, $preview[0]['values']['withholding_tax_adjustment']);
            $this->assertTrue($preview[0]['name_mismatch']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_imports_only_retained_legacy_summary_columns(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('SUMMARY SALARY');
        $sheet->setCellValue('B5', 742);
        $sheet->setCellValue('JA5', 1000);
        $sheet->setCellValue('JF5', 200);
        $sheet->setCellValue('JJ5', 300);
        $sheet->setCellValue('JN5', 400);
        $sheet->setCellValue('JU5', 500);
        $sheet->setCellValue('GC5', -25);
        $sheet->setCellValue('IX5', 9);
        $path = $this->saveWorkbook($book);

        try {
            $preview = (new TaxInputImportService)->preview($path);
            $this->assertTrue($preview[0]['valid']);
            $this->assertSame([
                'previous_basic' => 1000.0,
                'previous_hazard' => 200.0,
                'previous_subsistence' => 300.0,
                'previous_mandatory_deductions' => 400.0,
                'previous_tax_withheld' => 500.0,
                'withholding_tax_adjustment' => -25.0,
            ], $preview[0]['values']);
            $this->assertArrayNotHasKey('future_months', $preview[0]['values']);
        } finally {
            @unlink($path);
        }
    }

    private function saveWorkbook(Spreadsheet $book): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tax_input_test_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}
