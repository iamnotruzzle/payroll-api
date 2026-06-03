<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function userAccounts(): View
    {
        return view('admin.user-accounts');
    }

    public function rolesPermissions(): View
    {
        return view('admin.roles-permissions');
    }
}
