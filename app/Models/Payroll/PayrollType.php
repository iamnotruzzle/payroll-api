<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollType extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_types';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
