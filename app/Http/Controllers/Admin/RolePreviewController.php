<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RolePreviewController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->attributes->get('role_preview_allowed'), 403);
        $validated = $request->validate(['role_id' => ['required', 'integer']]);
        $role = Role::query()->where('guard_name', 'web')->where('is_active', true)
            ->where('name', '!=', 'super-admin')->find($validated['role_id']);
        abort_unless($role, 422, 'Choose an active role to preview.');
        $request->session()->put('role_preview_id', $role->id);

        return redirect()->route('home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('role_preview_id');

        return redirect()->route('home');
    }
}
