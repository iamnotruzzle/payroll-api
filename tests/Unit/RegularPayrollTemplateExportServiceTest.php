<?php

namespace Tests\Unit;

use App\Services\Payroll\RegularPayrollTemplateExportService;
use Illuminate\Support\Facades\Config;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RegularPayrollTemplateExportServiceTest extends TestCase
{
    public function test_it_exports_review_table_statutory_tax_loan_and_split_pay_values(): void
    {
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $path = app(RegularPayrollTemplateExportService::class)->export(
            collect([$this->payrollRow()]),
            collect(),
            collect(),
            '2026-05',
        );

        try {
            $sheet = IOFactory::load($path)->getSheetByName('Regular');

            $this->assertSame(6000.44, $sheet->getCell('AO7')->getValue());
            $this->assertSame(100.0, $sheet->getCell('AP7')->getValue());
            $this->assertSame(1000.55, $sheet->getCell('AR7')->getValue());
            $this->assertSame(25.0, $sheet->getCell('AT7')->getValue());
            $this->assertSame(200.66, $sheet->getCell('AU7')->getValue());
            $this->assertSame(50.0, $sheet->getCell('AV7')->getValue());
            $this->assertSame(0, $sheet->getCell('AW7')->getValue());
            $this->assertSame(210.1, $sheet->getCell('EO7')->getValue());
            $this->assertSame(1234.56, $sheet->getCell('EV7')->getValue());
            $this->assertSame(42080.13, $sheet->getCell('EX7')->getValue());
            $this->assertSame(21040.07, $sheet->getCell('EY7')->getValue());
            $this->assertSame(21040.06, $sheet->getCell('EZ7')->getValue());

            $this->assertSame('UOLI PREM.', $sheet->getCell('CE3')->getValue());
            $this->assertSame('OPTIONAL / AJ', $sheet->getCell('CJ3')->getValue());
            $this->assertSame('COCO', $sheet->getCell('EI3')->getValue());
            $this->assertSame('OTHER LOANS', $sheet->getCell('EK3')->getValue());
            $this->assertSame(11.11, $sheet->getCell('CE7')->getValue());
            $this->assertSame(22.22, $sheet->getCell('CJ7')->getValue());
            $this->assertSame(33.33, $sheet->getCell('EI7')->getValue());
            $this->assertSame(44.44, $sheet->getCell('EK7')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_it_exports_dynamic_compensation_adjustments_when_template_has_matching_columns(): void
    {
        Config::set('database.connections.payroll', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $templatePath = $this->templateWithHeader('AF3', 'Special Pay Differential');
        Config::set('payroll.regular_template_path', $templatePath);

        $row = $this->payrollRow();
        $row['compensation_adjustments']['extra_items'] = [
            '99' => [
                'key' => '99',
                'type_id' => 99,
                'type' => 'Special Pay Differential',
                'code' => 'SPECIAL_PAY_DIFFERENTIAL',
                'operator' => 'ADD',
                'amount' => 321.45,
                'signed_amount' => 321.45,
            ],
        ];

        $path = app(RegularPayrollTemplateExportService::class)->export(
            collect([$row]),
            collect(),
            collect(),
            '2026-05',
        );

        try {
            $sheet = IOFactory::load($path)->getSheetByName('Regular');

            $this->assertSame(321.45, $sheet->getCell('AF7')->getValue());
        } finally {
            @unlink($path);
            @unlink($templatePath);
        }
    }

    private function payrollRow(): array
    {
        return [
            'emp_id' => 'E-001',
            'division' => 'DIV',
            'department' => 'DEPT',
            'tin_no' => 'TIN',
            'fund_type' => 'FUND',
            'gsis_no' => 'GSIS',
            'phic_no' => 'PHIC',
            'hdmf_no' => 'HDMF',
            'employee_name' => 'Test Employee',
            'position' => 'Nurse',
            'salary_grade' => 12,
            'step' => 3,
            'deduction_days' => 1.25,
            'leave_deduction' => [
                'periods' => ['May 1'],
                'working_days' => 1,
                'calendar_days' => 1,
            ],
            'basic_salary' => 50000,
            'compensations' => [],
            'compensation_adjustments' => [
                'basic_salary' => 100,
                'subsistence' => 20,
                'laundry' => 30,
                'pera' => 40,
                'remarks' => 'Adjustment',
            ],
            'statutory_deductions' => [
                'life_retirement' => 4500.11,
                'phic' => 1000.22,
                'mandatory_pagibig' => 200.33,
                'hdmf_ps_2_ms' => 25.0,
                'ea_deduction' => 50.0,
            ],
            'statutory_government_shares' => [
                'government_life_retirement' => 6000.44,
                'ec' => 100.0,
                'government_phic' => 1000.55,
                'government_pagibig' => 200.66,
            ],
            'mandatory_deduction_adjustments' => [
                'items' => [
                    'life_retirement' => 10,
                    'government_life_retirement' => 20,
                    'ec' => 0,
                    'phic' => 0,
                    'government_phic' => 0,
                    'mandatory_pagibig' => 0,
                    'hdmf_ps_2_ms' => 0,
                    'government_pagibig' => 0,
                    'ea_deduction' => 2.34,
                ],
                'employee_total' => 12.34,
                'government_total' => 20,
            ],
            'mandatory_deduction_adjustment' => 12.34,
            'total_mandatory_deductions' => 5763.0,
            'tax' => [
                'monthly_mandatory_deductions' => 5700.66,
                'monthly_net_income' => 44399.34,
                'monthly_tax_due' => 1234.56,
                'hazard' => 0,
            ],
            'loan_deductions' => [
                'total' => 110.10,
                'columns' => [
                    'gsis_uoli' => 11.11,
                    'gsis_optional' => 22.22,
                    'coco' => 33.33,
                    'other_loans' => 44.44,
                ],
            ],
            'program_deductions' => [
                'total' => 100.0,
                'items' => [
                    [
                        'id' => 1,
                        'name' => 'MMSU',
                        'amount' => 100.0,
                    ],
                ],
            ],
            'gross' => 50000,
            'net_before_other_deductions' => 44300,
            'total_other_deductions' => 210.10,
            'net_after_tax' => 43065.44,
            'net_after_program_deductions' => 43065.44,
            'net_after_loan_deductions' => 42080.13,
            'fifteenth' => 21040.07,
            'thirtieth' => 21040.06,
        ];
    }

    private function templateWithHeader(string $cell, string $header): string
    {
        $spreadsheet = IOFactory::load(config('payroll.regular_template_path'));
        $spreadsheet->getSheetByName('Regular')->setCellValue($cell, $header);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'regular_payroll_template_'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
