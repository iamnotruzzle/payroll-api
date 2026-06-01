<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;

class PayrollPageController extends Controller
{
    public function dtr()
    {
        return redirect()->route('payroll.dtr-encoding');
    }

    public function dtrEncoding()
    {
        return view('payroll.dtr-encoding');
    }

    public function dtrCorrectionRequests()
    {
        return view('payroll.dtr-correction-requests');
    }

    public function dtrCorrectionApprovers()
    {
        return view('payroll.dtr-correction-approvers');
    }

    public function mra()
    {
        return view('payroll.mra');
    }

    public function generationConfiguration()
    {
        return view('payroll.generation-configuration');
    }

    public function generation()
    {
        return view('payroll.generation');
    }

    public function hazardGeneration()
    {
        return view('payroll.generation-hazard');
    }

    public function medicareGeneration()
    {
        return view('payroll.generation-medicare');
    }

    public function loanImports()
    {
        return view('payroll.loan-imports');
    }

    public function loanReferences()
    {
        return view('payroll.loan-references');
    }

    public function compensations()
    {
        return view('payroll.compensations');
    }

    public function adjustmentTypes()
    {
        return view('payroll.adjustment-types');
    }

    public function deductionPrograms()
    {
        return view('payroll.deduction-programs');
    }

    public function holidays()
    {
        return view('payroll.holidays');
    }

    public function history()
    {
        return view('payroll.history');
    }

    public function userManual()
    {
        return view('payroll.user-manual');
    }
}
