<?php

namespace App\Models\Payroll;

use App\Models\Payroll\Canonical\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class PayrollUserAccount extends Authenticatable
{
    use HasApiTokens,HasRoles;

    protected $connection = 'payroll';

    protected $table = 'payroll_user_accounts';

    protected $primaryKey = 'userid';

    protected string $guard_name = 'web';

    protected $fillable = ['source_batch_id', 'emp_id', 'username', 'password', 'login_attempt', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed', 'is_active' => 'boolean', 'login_attempt' => 'integer'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
