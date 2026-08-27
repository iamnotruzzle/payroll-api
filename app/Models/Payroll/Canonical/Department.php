<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_departments';

    protected $fillable = ['source_batch_id', 'external_id', 'division_external_id', 'name', 'is_active'];

    public function getDepartmentIdAttribute()
    {
        return $this->external_id;
    }

    public function getDivisionIdAttribute()
    {
        return $this->division_external_id;
    }

    public function getDepartmentAttribute()
    {
        return $this->name;
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_external_id', 'external_id');
    }
}
