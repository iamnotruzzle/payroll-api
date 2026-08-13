<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Hris\UserAccount;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends MobileController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'emp_id' => ['required_without:username', 'nullable', 'string'],
            'username' => ['required_without:emp_id', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim((string) ($request->input('emp_id') ?: $request->input('username')));

        $user = UserAccount::query()
            ->with(['employee.department', 'employee.position'])
            ->where(function ($query) use ($login) {
                $query->where('emp_id', $login)->orWhere('username', $login);
            })
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->employee?->is_active !== 'Y') {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $token = $user->createToken('payroll-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var UserAccount $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }
}
