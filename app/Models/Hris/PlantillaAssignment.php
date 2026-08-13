<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaAssignment extends Model
{
    protected $connection = 'hris';

    protected $fillable = ['plantilla_item_id', 'emp_id', 'effective_from', 'effective_to', 'nature', 'remarks', 'recorded_by_emp_id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlantillaItem::class, 'plantilla_item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
