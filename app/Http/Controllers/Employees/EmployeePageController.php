<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\HrisV2\EmployeeDocument;
use App\Support\Hris\EmployeeDirectoryQuery;
use App\Support\Hris\PdsPrintPresenter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePageController extends Controller
{
    public function index(): View
    {
        return view('employees.index');
    }

    public function show(string $empId): View
    {
        return view('employees.show', [
            'empId' => $empId,
        ]);
    }

    public function print(string $empId, PdsPrintPresenter $presenter): View
    {
        return view('employees.print', $presenter->present(
            $empId,
            route('employees.show', $empId),
        ));
    }

    public function downloadDocument(string $empId, int $documentId): StreamedResponse
    {
        abort_unless(auth()->user()?->can('employees.view') || auth()->user()?->can('employees.manage'), 403);
        abort_unless(EmployeeDirectoryQuery::usesV2(), 404);

        $document = EmployeeDocument::query()
            ->where('emp_id', $empId)
            ->whereKey($documentId)
            ->firstOrFail();

        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
