<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollDtrCorrectionRequest;
use App\Services\Payroll\DtrCorrectionRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DtrCorrectionRequestController extends Controller
{
    public function __construct(private DtrCorrectionRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollDtrCorrectionRequest::query()
            ->with(['employee', 'requestedBy', 'approver']);

        $this->applyFilters($query, $request);

        return response()->json($query
            ->orderByDesc('requested_at')
            ->paginate(
                (int) $request->get('per_page', 15),
                ['*'],
                'page',
                (int) $request->get('page', 1)
            ));
    }

    public function approvers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string'],
        ]);

        return response()->json($this->service->approversFor($data['emp_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string'],
            'dtr_date' => ['required', 'date'],
            'request_type' => ['required', Rule::in([
                PayrollDtrCorrectionRequest::TYPE_TIME_IN,
                PayrollDtrCorrectionRequest::TYPE_TIME_OUT,
                PayrollDtrCorrectionRequest::TYPE_BOTH,
            ])],
            'requested_time_in' => ['nullable', 'date_format:H:i'],
            'requested_time_out' => ['nullable', 'date_format:H:i'],
            'requested_timeout_nextday' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
            'approver_emp_id' => ['nullable', 'string'],
            'requested_by_emp_id' => ['nullable', 'string'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('dtr-correction-requests', 'public');
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_mime_type'] = $file->getMimeType();
            $data['attachment_size'] = $file->getSize();
        }

        $correctionRequest = $this->service->create($data, $this->actorEmpId($request, 'requested_by_emp_id'));

        return response()->json($correctionRequest, 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'approved_by_emp_id' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'request_type' => ['nullable', Rule::in([
                PayrollDtrCorrectionRequest::TYPE_TIME_IN,
                PayrollDtrCorrectionRequest::TYPE_TIME_OUT,
                PayrollDtrCorrectionRequest::TYPE_BOTH,
            ])],
            'requested_time_in' => ['nullable', 'date_format:H:i'],
            'requested_time_out' => ['nullable', 'date_format:H:i'],
            'requested_timeout_nextday' => ['nullable', 'boolean'],
        ]);

        $correctionRequest = PayrollDtrCorrectionRequest::findOrFail($id);
        $adjustments = collect($data)->only([
            'request_type',
            'requested_time_in',
            'requested_time_out',
            'requested_timeout_nextday',
        ])->all();

        return response()->json($this->service->approve(
            $correctionRequest,
            $this->actorEmpId($request, 'approved_by_emp_id'),
            $data['remarks'] ?? null,
            $adjustments ?: null
        ));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'updated_by_emp_id' => ['nullable', 'string'],
            'dtr_date' => ['required', 'date'],
            'request_type' => ['required', Rule::in([
                PayrollDtrCorrectionRequest::TYPE_TIME_IN,
                PayrollDtrCorrectionRequest::TYPE_TIME_OUT,
                PayrollDtrCorrectionRequest::TYPE_BOTH,
            ])],
            'requested_time_in' => ['nullable', 'date_format:H:i'],
            'requested_time_out' => ['nullable', 'date_format:H:i'],
            'requested_timeout_nextday' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $correctionRequest = PayrollDtrCorrectionRequest::findOrFail($id);

        return response()->json($this->service->update(
            $correctionRequest,
            $this->actorEmpId($request, 'updated_by_emp_id'),
            $data
        ));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cancelled_by_emp_id' => ['nullable', 'string'],
        ]);

        $correctionRequest = PayrollDtrCorrectionRequest::findOrFail($id);

        return response()->json($this->service->cancel(
            $correctionRequest,
            $this->actorEmpId($request, 'cancelled_by_emp_id')
        ));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rejected_by_emp_id' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $correctionRequest = PayrollDtrCorrectionRequest::findOrFail($id);

        return response()->json($this->service->reject(
            $correctionRequest,
            $this->actorEmpId($request, 'rejected_by_emp_id'),
            $data['remarks'] ?? null
        ));
    }

    private function actorEmpId(Request $request, string $field): string
    {
        $actor = auth()->user()?->emp_id ?? $request->input($field);

        if (blank($actor)) {
            throw ValidationException::withMessages([
                $field => 'Employee actor is required.',
            ]);
        }

        return (string) $actor;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('emp_id')) {
            $query->where('emp_id', $request->get('emp_id'));
        }

        if ($request->filled('approver_emp_id')) {
            $query->where('approver_emp_id', $request->get('approver_emp_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->get('department_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->get('status')));
        }

        if ($request->filled('from')) {
            $query->whereDate('dtr_date', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('dtr_date', '<=', $request->get('to'));
        }
    }
}
