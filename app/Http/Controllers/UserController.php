<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        // Use Eloquent with relationships
        $query = User::with('roles');

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status_filter') && $request->get('status_filter') !== 'all') {
            $query->where('status', $request->get('status_filter'));
        }

        // Apply role filter
        if ($request->filled('role_filter') && $request->get('role_filter') !== 'all') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->get('role_filter'));
            });
        }

        // Apply gender filter
        if ($request->filled('gender_filter') && $request->get('gender_filter') !== 'all') {
            $query->where('gender', $request->get('gender_filter'));
        }

        // Apply date range filter
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = \Carbon\Carbon::parse($request->get('date_from'))->startOfDay();
            $dateTo = \Carbon\Carbon::parse($request->get('date_to'))->endOfDay();
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        // Get total count
        $totalCount = $query->count();

        // Apply sorting
        $sortBy = $request->get('sort_by', 'first_name');
        $sortOrder = $request->get('sort_order', 'asc');
        $allowedSortFields = ['first_name', 'last_name', 'username', 'email', 'status', 'gender', 'birthdate', 'created_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('first_name', 'asc');
        }

        // Get results with pagination
        $users = $query->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'suffix' => $user->suffix,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'gender' => $user->gender,
                    'birthdate' => $user->birthdate,
                    'birthdate_formatted' => $user->birthdate_formatted,
                    'username' => $user->username,
                    'roles' => $user->roles->map(fn($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ]),
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

        // Calculate pagination info
        $lastPage = ceil($totalCount / $perPage);
        $from = $totalCount > 0 ? ($currentPage - 1) * $perPage + 1 : 0;
        $to = min($currentPage * $perPage, $totalCount);

        return response()->json([
            'data' => $users,
            'current_page' => (int) $currentPage,
            'per_page' => (int) $perPage,
            'total' => (int) $totalCount,
            'last_page' => (int) $lastPage,
            'from' => (int) $from,
            'to' => (int) $to,
        ]);
    }

    public function statusCounts(): JsonResponse
    {
        $counts = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
        ];

        return response()->json($counts);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'required|email|max:255|unique:users,email',
            'gender' => 'required|in:male,female',
            'birthdate' => 'required|date|before:today',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name', // Single role
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Create user
        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
        ]);

        // Assign single role
        $user->syncRoles([$validated['role']]);

        // Load relationships for response
        $user->load('roles');

        return response()->json($user, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'gender' => 'required|in:male,female',
            'birthdate' => 'required|date|before:today',
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|string|exists:roles,name', // Single role
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Update user
        $user->update([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'email' => $validated['email'],
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'username' => $validated['username'],
            'status' => $validated['status'],
        ]);

        // Update password if provided
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->save();
        }

        // Sync single role (replace old role)
        $user->syncRoles([$validated['role']]);

        // Load relationships for response
        $user->load('roles');

        return response()->json($user);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}
