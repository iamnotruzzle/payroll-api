<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function hrisApiDiagnostics(): View
    {
        abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->hasRole('admin'), 403);

        return view('admin.hris-api-diagnostics');
    }

    public function userAccounts(): View
    {
        return view('admin.user-accounts');
    }

    public function rolesPermissions(): View
    {
        return view('admin.roles-permissions');
    }

    public function payrollSystem(): View
    {
        abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('payroll.system.import'), 403);

        return view('admin.payroll-system');
    }
}
