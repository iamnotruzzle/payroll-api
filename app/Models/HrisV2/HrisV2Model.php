<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Model;

abstract class HrisV2Model extends Model
{
    protected $connection = 'hris_v2';
}
