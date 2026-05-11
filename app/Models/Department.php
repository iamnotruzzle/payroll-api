<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tbl_department';
    protected $primaryKey = 'department_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'department',
        'division_id',
    ];

    protected function casts(): array
    {
        return [
            'division_id' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id', 'division_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id', 'department_id');
    }
}