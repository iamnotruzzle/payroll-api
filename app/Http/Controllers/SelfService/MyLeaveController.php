<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MyLeaveController extends Controller
{
    public function index(): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        return view('self-service.my-leave', [
            'empId' => $empId,
        ]);
    }
}
