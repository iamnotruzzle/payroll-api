<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class HistoricalPayrollImportSheet extends Model
{
    protected $connection = 'payroll';

    protected $fillable = [
        'historical_payroll_import_id', 'sheet_name', 'header_row', 'included', 'division_id',
        'department_id', 'row_count', 'matched_count', 'difference_count', 'column_map',
        'organization_mappings',
    ];

    protected $casts = ['included' => 'boolean', 'column_map' => 'array', 'organization_mappings' => 'array'];

    public function import()
    {
        return $this->belongsTo(HistoricalPayrollImport::class, 'historical_payroll_import_id');
    }

    public function rows()
    {
        return $this->hasMany(HistoricalPayrollImportRow::class);
    }
}
