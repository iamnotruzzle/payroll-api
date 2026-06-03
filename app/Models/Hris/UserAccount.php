<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class UserAccount extends Authenticatable
{
    use HasRoles;

    protected $connection = 'mysql';
    protected $table = 'tbl_useraccount';
    protected $primaryKey = 'userid';
    protected string $guard_name = 'web';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'emp_id',
        'username',
        'password',
        'remember_token',
        'login_attempt',
        'user_level',
        'created_by',
        'pims_role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'login_attempt' => 'integer',
            'user_level'    => 'integer',
            'created_by'    => 'integer',
            'pims_role'     => 'integer',
            'password'      => 'hashed',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
