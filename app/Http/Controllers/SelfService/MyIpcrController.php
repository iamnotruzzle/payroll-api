<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MyIpcrController extends Controller
{
    public function index(): View
    {
        return view('self-service.my-ipcr', [
            'empId' => (string) (auth()->user()?->emp_id ?? ''),
        ]);
    }
}
