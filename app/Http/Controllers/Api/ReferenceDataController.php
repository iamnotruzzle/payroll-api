<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hris\Department;
use App\Models\Hris\PayrollItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    public function departments(): JsonResponse
    {
        return response()->json(
            Department::query()
                ->orderBy('department')
                ->get()
        );
    }

    public function payrollItems(Request $request): JsonResponse
    {
        $query = PayrollItem::query();

        if ($request->filled('calculation')) {
            $query->where('calculation', $request->get('calculation'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->get('q'));
            $query->where(function ($builder) use ($term) {
                $builder->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('calculation', 'like', "%{$term}%");
            });
        }

        $sortMap = [
            'code' => 'code',
            'name' => 'name',
            'calculation' => 'calculation',
        ];
        $sort = $sortMap[$request->get('sort', 'name')] ?? 'name';
        $direction = strtolower((string) $request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        return response()->json($query->orderBy($sort, $direction)->paginate(
            (int) $request->get('per_page', 10),
            ['*'],
            'page',
            (int) $request->get('page', 1)
        ));
    }

    public function payrollItemCalculations(): JsonResponse
    {
        return response()->json(
            PayrollItem::query()
                ->where('calculation', '!=', '')
                ->select('calculation')
                ->distinct()
                ->orderBy('calculation')
                ->pluck('calculation')
        );
    }
}
