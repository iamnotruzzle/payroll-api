<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    protected $connection = 'hris';

    protected $table = 'tbl_employmentstat';

    protected $primaryKey = 'empstat_id';

    public $timestamps = false;

    protected $fillable = [
        'status',
    ];
}
