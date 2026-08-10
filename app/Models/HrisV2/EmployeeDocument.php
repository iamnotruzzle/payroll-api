<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends HrisV2Model
{
    protected $table = 'employee_documents';

    protected $fillable = [
        'employee_id',
        'emp_id',
        'category',
        'title',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by_emp_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
