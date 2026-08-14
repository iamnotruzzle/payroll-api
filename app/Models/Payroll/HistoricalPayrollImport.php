<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class HistoricalPayrollImport extends Model
{
    protected $connection = 'payroll';

    protected $fillable = [
        'original_filename', 'stored_path', 'file_hash', 'payroll_period', 'payroll_type_code',
        'status', 'sheet_count', 'row_count', 'matched_count', 'difference_count', 'comparison_draft_id',
        'comparison_configuration', 'comparison_drafts', 'workflow_state', 'created_by', 'applied_at',
    ];

    protected $casts = [
        'comparison_configuration' => 'array', 'comparison_drafts' => 'array', 'workflow_state' => 'array',
        'applied_at' => 'datetime',
    ];

    public function sheets()
    {
        return $this->hasMany(HistoricalPayrollImportSheet::class);
    }
}
