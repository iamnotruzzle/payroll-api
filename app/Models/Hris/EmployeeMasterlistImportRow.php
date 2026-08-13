<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMasterlistImportRow extends Model
{
    protected $connection = 'hris';

    protected $fillable = [
        'import_id', 'source_row', 'emp_id', 'action', 'status', 'selected', 'source_payload',
        'changes', 'warnings', 'errors', 'resolved_position_id', 'resolved_department_id',
        'resolved_empstat_id', 'preview_employee_updated_at', 'row_hash', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean', 'source_payload' => 'array', 'changes' => 'array',
            'warnings' => 'array', 'errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(EmployeeMasterlistImport::class, 'import_id');
    }
}
