<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use App\Support\Hris\PdsPrintPresenter;
use Illuminate\View\View;

class MyProfileController extends Controller
{
    public function show(): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        return view('self-service.my-profile', [
            'empId' => $empId,
        ]);
    }

    public function print(PdsPrintPresenter $presenter): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        return view('employees.print', $presenter->present(
            $empId,
            route('self-service.profile'),
        ));
    }
}
