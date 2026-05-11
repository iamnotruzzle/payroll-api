<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class LeaveStatusLookup extends Model
{
    protected $table = 'tbl_leave_status';
    protected $primaryKey = 'status_id';
    public $timestamps = false;

    protected $fillable = [
        'status_name',
    ];
}
