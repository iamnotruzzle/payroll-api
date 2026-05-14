<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollBankTemplate;
use App\Services\Payroll\PayrollBankTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollBankTemplateController extends Controller
{
    public function __construct(private PayrollBankTemplateService $service) {}

    /**
     * GET /payroll-bank-template
     * Returns the latest saved template (or 404 if none exists).
     */
    public function show(): JsonResponse
    {
        $template = $this->service->getLatest();

        if (!$template) {
            return response()->json(['message' => 'No template found.'], 404);
        }

        return response()->json($template);
    }

    /**
     * POST /payroll-bank-template
     * Save a new template snapshot.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns'                 => 'required|array|min:1',
            'columns.*.column_key'    => 'required|string|max:100',
            'columns.*.label'         => 'required|string|max:255',
            'columns.*.width'         => 'sometimes|integer|min:50|max:500',
        ]);

        $template = $this->service->save($data['columns']);

        return response()->json($template, 201);
    }

    /**
     * PUT /payroll-bank-template/{id}
     * Update an existing template's columns.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'columns'                 => 'required|array|min:1',
            'columns.*.column_key'    => 'required|string|max:100',
            'columns.*.label'         => 'required|string|max:255',
            'columns.*.width'         => 'sometimes|integer|min:50|max:500',
        ]);

        $template = $this->service->updateColumns($id, $data['columns']);

        return response()->json($template);
    }

    /**
     * GET /payroll-bank-template/{id}/download
     * Download the template as a blank Excel file.
     */
    public function download(int $id): BinaryFileResponse
    {
        $template = PayrollBankTemplate::with('columns')->findOrFail($id);

        $path = $this->service->buildExcel($template);

        $filename = 'bank_template_' . $template->created_at->format('Ymd_His') . '.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
