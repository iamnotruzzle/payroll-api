<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollExport extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_exports';
    public $timestamps = false;

    protected $fillable = [
        'payroll_generate_id',
        'export_type',
        'file_name',
        'file_path',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];
}
