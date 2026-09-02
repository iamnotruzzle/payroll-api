<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class SuperAdminQuickSwitch extends Component
{
    public string $password = '';

    public function elevate()
    {
        abort_unless(auth()->check(), 403);

        $this->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = 'super-admin-switch:'.auth()->id().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('password', "Too many attempts. Try again in {$seconds} seconds.");

            return null;
        }

        $hash = (string) config('payroll_standalone.super_admin_switch_password_hash');
        if ($hash === '' || ! Hash::check($this->password, $hash)) {
            RateLimiter::hit($key, 60);
            $this->password = '';
            $this->addError('password', 'The super-admin switch password is incorrect.');

            return null;
        }

        RateLimiter::clear($key);
        session()->migrate(true);
        session()->put('super_admin_elevated', true);
        $this->password = '';

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.super-admin-quick-switch');
    }
}
