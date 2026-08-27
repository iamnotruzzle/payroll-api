<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_leave_types';

    protected $fillable = ['source_batch_id', 'external_id', 'name', 'is_active'];

    public function getLeaveTypeIdAttribute()
    {
        return $this->external_id;
    }

    public function getLeaveNameAttribute()
    {
        return $this->name;
    }
}
