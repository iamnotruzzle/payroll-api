<?php

namespace App\Models;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $connection = 'payroll';

    public function getConnectionName()
    {
        try {
            return Schema::connection('payroll')->hasTable('permissions') ? 'payroll' : 'hris';
        } catch (\Throwable) {
            return 'hris';
        }
    }
}
