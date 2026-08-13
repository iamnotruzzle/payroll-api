<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class HrisReferenceMetadata extends Model
{
    protected $connection = 'hris';

    protected $table = 'hris_reference_metadata';

    protected $fillable = ['reference_type', 'reference_id', 'is_active', 'remarks', 'updated_by_emp_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'reference_id' => 'integer'];
    }
}
