<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaItem extends Model
{
    protected $connection = 'hris';

    protected $fillable = ['item_number', 'position_id', 'department_id', 'salary_grade', 'fund_type', 'authorization_year', 'status', 'effective_from', 'effective_to', 'remarks', 'updated_by_emp_id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'salary_grade' => 'integer', 'authorization_year' => 'integer'];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PlantillaAssignment::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(PlantillaAssignment::class)->whereNull('effective_to');
    }
}
