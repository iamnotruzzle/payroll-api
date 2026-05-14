<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;

class PayrollPageController extends Controller
{
    public function dtr()
    {
        return view('payroll.dtr');
    }

    public function dtrEncoding()
    {
        return view('payroll.dtr-encoding');
    }

    public function mra()
    {
        return view('payroll.mra');
    }

    public function generation()
    {
        return view('payroll.generation');
    }

    public function compensations()
    {
        return view('payroll.compensations');
    }
}
