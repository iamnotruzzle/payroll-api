<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollSystemSetting extends Model
{
    protected $connection = 'payroll';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = ['value' => 'array'];
}
