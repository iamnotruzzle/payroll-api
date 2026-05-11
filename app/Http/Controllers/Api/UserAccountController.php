<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hris\UserAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    public function __construct(private UserAccountService $service) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 20),
            search: $request->get('q'),
            sort: $request->get('sort', 'username'),
            direction: $request->get('direction', 'asc'),
        );

        return response()->json($result);
    }
}
