<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $connection = 'hris';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active',
    ];
}
