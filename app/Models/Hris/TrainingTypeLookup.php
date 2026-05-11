<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class TrainingTypeLookup extends Model
{
    protected $table = 'tbl_training_types';
    public $timestamps = false;

    protected $fillable = [
        'type',
        'description',
    ];
}
