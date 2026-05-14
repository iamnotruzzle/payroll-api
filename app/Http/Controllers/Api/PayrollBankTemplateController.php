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


    //  List all templates (id + created_at), newest first.
    public function index(): JsonResponse
    {
        return response()->json($this->service->getAll());
    }

    // Get the latest template with its columns.
    public function latest(): JsonResponse
    {
        $template = $this->service->getLatest();

        if (!$template) {
            return response()->json(['message' => 'No template found.'], 404);
        }

        return response()->json($template);
    }

    //Get a specific template with its columns.
    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->getById($id));
    }

    //Always creates a new snapshot — never overwrites.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns'              => 'required|array|min:1',
            'columns.*.column_key' => 'required|string|max:100',
            'columns.*.label'      => 'required|string|max:255',
            'columns.*.width'      => 'sometimes|integer|min:50|max:500',
        ]);

        $template = $this->service->save($data['columns']);

        return response()->json($template, 201);
    }
    // Delete a template snapshot from history.
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Deleted.']);
    }

    //  Download a template as a blank Excel file.
    public function download(int $id): BinaryFileResponse
    {
        $template = $this->service->getById($id);
        $path     = $this->service->buildExcel($template);
        $filename = 'bank_template_' . $template->created_at->format('Ymd_His') . '.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
