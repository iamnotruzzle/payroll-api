<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_positions';

    protected $fillable = ['source_batch_id', 'external_id', 'title', 'salary_grade', 'remarks', 'is_active'];

    protected $casts = ['salary_grade' => 'integer'];

    public function getPositionIdAttribute()
    {
        return $this->external_id;
    }

    public function getPositionTitleAttribute()
    {
        return $this->title;
    }
}
