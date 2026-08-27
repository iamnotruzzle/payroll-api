<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_divisions';

    protected $fillable = ['source_batch_id', 'external_id', 'name', 'is_active'];

    public function getDivisionIdAttribute()
    {
        return $this->external_id;
    }

    public function getDivisionAttribute()
    {
        return $this->name;
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'division_external_id', 'external_id');
    }
}
