<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'tbl_leave_type';
    protected $primaryKey = 'leave_type_id';
    public $timestamps = false;

    protected $fillable = [
        'leave_name',
        'description',
        'max_value',
        'to_display',
        'processable',
    ];

    protected $casts = [
        'to_display' => 'boolean',
    ];
}
