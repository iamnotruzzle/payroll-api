<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'emp_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['emp_id' => 'Invalid employee ID or password.'])
                ->onlyInput('emp_id');
        }

        $request->session()->regenerate();

        $user = $request->user()?->loadMissing('employee');

        if ($user?->employee?->is_active !== 'Y') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['emp_id' => 'This account is inactive.'])
                ->onlyInput('emp_id');
        }

        return redirect()->intended(route(match (true) {
            $user->can('schedule.view') => 'schedule.dashboard',
            $user->can('payroll.view') => 'payroll.generation.configuration',
            $user->can('timekeeping.view') => 'payroll.dtr-encoding',
            $user->can('admin.users.view') => 'admin.user-accounts',
            $user->can('admin.roles.view') => 'admin.roles-permissions',
            default => 'access.pending',
        }));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
