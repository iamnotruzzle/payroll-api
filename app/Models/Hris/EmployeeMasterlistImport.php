<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeMasterlistImport extends Model
{
    protected $connection = 'hris';

    protected $fillable = [
        'original_name', 'stored_path', 'file_hash', 'sheet_name', 'status', 'effective_date',
        'options', 'total_rows', 'new_rows', 'changed_rows', 'unchanged_rows', 'warning_rows',
        'error_rows', 'applied_rows', 'failed_rows', 'imported_by_emp_id', 'applied_at',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'options' => 'array', 'applied_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(EmployeeMasterlistImportRow::class, 'import_id');
    }
}
