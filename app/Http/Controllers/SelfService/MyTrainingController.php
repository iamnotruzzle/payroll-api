<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MyTrainingController extends Controller
{
    public function index(): View
    {
        return view('self-service.my-training', [
            'empId' => (string) (auth()->user()?->emp_id ?? ''),
        ]);
    }
}
