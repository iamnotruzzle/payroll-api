<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tbl_salary_grade';
    protected $primaryKey = 'salary_grade_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'tranche_number',
        'salary_grade',
        'step_increment',
        'salary',
        'effectivity_date',
    ];

    protected function casts(): array
    {
        return [
            'tranche_number'   => 'integer',
            'salary_grade'     => 'integer',
            'step_increment'   => 'integer',
            'salary'           => 'decimal:2',
            'effectivity_date' => 'date',
        ];
    }
}
