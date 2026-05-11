<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tbl_division';
    protected $primaryKey = 'division_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'division',
        'emp_id',
        'special_title',
        'updated_by',
        'updated_date',
    ];

    protected function casts(): array
    {
        return [
            'updated_date' => 'datetime',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'division_id', 'division_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
