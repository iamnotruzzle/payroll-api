<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $connection = 'hris';

    protected $table = 'employee_documents';

    protected $fillable = [
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
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
