<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class HistoricalPayrollImportRow extends Model
{
    protected $connection = 'payroll';

    protected $fillable = [
        'historical_payroll_import_sheet_id', 'source_row', 'source_employee_no', 'source_employee_name',
        'source_division', 'source_department', 'organization_key',
        'matched_emp_id', 'match_status', 'comparison_status', 'workbook_values', 'system_values',
        'differences', 'source_values',
    ];

    protected $casts = [
        'workbook_values' => 'array', 'system_values' => 'array', 'differences' => 'array', 'source_values' => 'array',
    ];

    public function sheet()
    {
        return $this->belongsTo(HistoricalPayrollImportSheet::class, 'historical_payroll_import_sheet_id');
    }
}
