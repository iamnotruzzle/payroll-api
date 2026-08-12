<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use App\Services\Payroll\DailyTimeRecordPrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyDtrController extends Controller
{
    public function index(): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        return view('self-service.my-dtr', [
            'empId' => $empId,
        ]);
    }

    public function print(Request $request, DailyTimeRecordPrintService $dtrPrintService): Response
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $payload = $dtrPrintService->buildPrintPayload(
            $empId,
            (int) $data['month'],
            (int) $data['year']
        );

        abort_unless((string) $payload['employee']->emp_id === $empId, 403);

        return $dtrPrintService->pdfResponse($payload);
    }
}
