<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class Eligibilities extends Model
{
    protected $table = 'tbl_eligibilities';
    protected $primaryKey = 'e_id';
    public $timestamps = false;

    protected $fillable = [
        'e_title',
    ];
}
