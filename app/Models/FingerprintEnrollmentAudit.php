<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintEnrollmentAudit extends Model
{
    protected $connection = 'hris';

    protected $fillable = [
        'employee_id', 'slot', 'action', 'previous_hash', 'new_hash',
        'template_length', 'quality', 'reader_model', 'reader_serial',
        'performed_by', 'ip_address',
    ];
}
