<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $connection = 'payroll';

    protected string $guard_name = 'web';

    public function getConnectionName()
    {
        try {
            return Schema::connection('payroll')->hasTable('roles') ? 'payroll' : 'hris';
        } catch (\Throwable) {
            return 'hris';
        }
    }
}
