<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'tbl_position';
    protected $primaryKey = 'position_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'position_title',
        'salary_grade',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'salary_grade' => 'integer',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id', 'position_id');
    }
}
