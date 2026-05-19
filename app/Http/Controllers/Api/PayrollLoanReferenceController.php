<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollLoanEntity;
use App\Models\Payroll\PayrollLoanType;
use App\Services\Payroll\PayrollLoanReferenceService;
use Illuminate\Http\JsonResponse;

class PayrollLoanReferenceController extends Controller
{
    public function index(PayrollLoanReferenceService $service): JsonResponse
    {
        return response()->json([
            'entities' => PayrollLoanEntity::query()
                ->with(['loanTypes' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'loan_types' => PayrollLoanType::query()
                ->with('entity')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'review_column_groups' => $service->columnGroups(),
        ]);
    }
}
