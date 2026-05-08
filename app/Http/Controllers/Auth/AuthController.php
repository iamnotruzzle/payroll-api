<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = UserAccount::with('employee.department.division', 'employee.position')
            ->where('username', $request->username)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->employee?->is_active !== 'Y') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        return response()->json([
            'userid'     => $user->userid,
            'emp_id'     => $user->emp_id,
            'username'   => $user->username,
            'employee'   => $user->employee,
        ]);
    }
}
