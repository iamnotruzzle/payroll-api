<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | MMMHMC HRIS &amp; Payroll</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <script>
        (() => {
            const saved = localStorage.getItem('erp-theme');
            document.documentElement.dataset.theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="erp-body min-h-screen antialiased">
    <button type="button" class="erp-theme-toggle erp-theme-toggle-floating" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme">
        <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
        <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <main class="erp-login min-h-screen lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,28rem)] xl:grid-cols-[minmax(0,1.2fr)_minmax(24rem,30rem)]">
        <aside class="erp-login-hero relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-12 xl:px-16">
            <div class="erp-login-hero-copy relative z-10 max-w-xl">
                <div class="erp-login-brand-row flex items-center gap-3">
                    <x-brand.mark size="xl" />
                    <p class="erp-brand-eyebrow text-xs font-bold uppercase">MMMHMC</p>
                </div>

                <h1 class="erp-login-product mt-8 text-5xl font-bold tracking-tight xl:text-6xl">
                    HRIS &amp; Payroll
                </h1>
                <p class="erp-login-lead mt-4 max-w-md text-base leading-relaxed">
                    One secure workspace for workforce records, timekeeping, scheduling, and payroll operations.
                </p>
            </div>

            <div class="erp-login-visual relative z-10 mt-auto" aria-hidden="true">
                <div class="erp-login-orbit erp-login-orbit-a"></div>
                <div class="erp-login-orbit erp-login-orbit-b"></div>
                <div class="erp-login-panel-stack">
                    <div class="erp-login-stack-card erp-login-stack-card-a">
                        <span class="erp-login-stack-label">Workforce</span>
                        <span class="erp-login-stack-value">Employee records</span>
                    </div>
                    <div class="erp-login-stack-card erp-login-stack-card-b">
                        <span class="erp-login-stack-label">Timekeeping</span>
                        <span class="erp-login-stack-value">Attendance &amp; DTR</span>
                    </div>
                    <div class="erp-login-stack-card erp-login-stack-card-c">
                        <span class="erp-login-stack-label">Payroll</span>
                        <span class="erp-login-stack-value">Runs &amp; disbursement</span>
                    </div>
                </div>
            </div>
        </aside>

        <section class="erp-login-panel relative flex min-h-screen items-center justify-center px-4 py-10 sm:px-8">
            <div class="erp-login-card w-full max-w-[26rem]">
                <div class="mb-7 lg:mb-8">
                    <div class="mb-5 flex items-center gap-3 lg:hidden">
                        <x-brand.mark size="lg" />
                        <div>
                            <p class="erp-brand-eyebrow text-[10px] font-bold uppercase">MMMHMC</p>
                            <p class="erp-brand-title text-base font-bold">HRIS &amp; Payroll</p>
                        </div>
                    </div>

                    <h2 class="erp-login-form-title text-2xl font-bold tracking-tight">Sign in</h2>
                    <p class="erp-subtle mt-1.5 text-sm">Use your employee credentials to continue.</p>
                </div>

                @if ($errors->any())
                    <div role="alert" class="erp-login-alert mb-5 rounded-xl border px-3.5 py-3 text-sm">
                        <p class="font-semibold">Unable to sign in</p>
                        <p class="mt-0.5 opacity-90">{{ $errors->first() }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                    @csrf

                    <label class="form-control w-full">
                        <span class="erp-login-label mb-1.5 block text-xs font-semibold">Employee ID</span>
                        <input
                            id="emp_id"
                            name="emp_id"
                            value="{{ old('emp_id') }}"
                            autocomplete="username"
                            autofocus
                            required
                            class="input input-bordered erp-login-input w-full"
                            placeholder="Enter employee ID"
                        >
                    </label>

                    <label class="form-control w-full">
                        <span class="erp-login-label mb-1.5 block text-xs font-semibold">Password</span>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="input input-bordered erp-login-input w-full pr-12"
                                placeholder="Enter password"
                            >
                            <button
                                type="button"
                                class="erp-login-reveal absolute inset-y-0 right-0 grid w-11 place-items-center"
                                data-password-toggle
                                aria-label="Show password"
                                aria-controls="password"
                            >
                                <svg data-password-icon="show" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg data-password-icon="hide" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a18.6 18.6 0 0 1-2.16 3.19M1 1l22 22M14.12 14.12A3 3 0 0 1 9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </label>

                    <label class="erp-login-remember flex cursor-pointer items-center gap-2.5 pt-1 text-sm">
                        <input name="remember" type="checkbox" value="1" class="erp-login-checkbox">
                        <span>Keep me signed in</span>
                    </label>

                    <button type="submit" class="btn erp-login-submit mt-2 w-full border-0 text-white">
                        Sign in
                    </button>
                </form>

                <p class="erp-login-footnote mt-8 text-center text-xs">
                    Authorized personnel only. Access is monitored for workforce and payroll security.
                </p>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.getAttribute('aria-controls'));
                if (!input) return;
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                button.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', !showing);
                button.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', showing);
            });
        });
    </script>
</body>
</html>
