<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Secure access to MMMHMC employee records, HR services, timekeeping, and payroll.">
    <meta name="theme-color" content="#f3f6f8">
    <title>Sign in | MMMHMC HRIS &amp; Payroll</title>
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
<body class="erp-body erp-portal-body antialiased">
    <a class="erp-skip-link" href="#signin">Skip to sign in</a>

    <header class="erp-portal-header">
        <a href="{{ route('login') }}" class="erp-portal-brand" aria-label="MMMHMC HRIS and Payroll home">
            <x-brand.mark size="lg" />
            <span>
                <strong>MMMHMC</strong>
                <small>HRIS &amp; Payroll</small>
            </span>
        </a>

        <button type="button" class="erp-portal-theme" data-theme-toggle aria-label="Switch to dark mode">
            <span class="erp-portal-theme-light">Light</span>
            <span class="erp-portal-theme-dark">Dark</span>
            <span aria-hidden="true" class="erp-portal-theme-control"></span>
        </button>
    </header>

    <main class="erp-portal-main">
        <section class="erp-portal-intro" aria-labelledby="portal-title">
            <div class="erp-portal-copy" data-portal-reveal>
                <h1 id="portal-title">Your workday.<br><span>Connected.</span></h1>
                <p>One secure place for employee records, schedules, timekeeping, leave, payslips, and payroll.</p>
            </div>

            <div class="erp-portal-system" data-portal-system data-portal-reveal aria-hidden="true">
                <div class="erp-portal-system-field">
                    <span class="erp-portal-system-orbit erp-portal-system-orbit-a" data-portal-orbit></span>
                    <span class="erp-portal-system-orbit erp-portal-system-orbit-b" data-portal-orbit></span>
                    <span class="erp-portal-system-link erp-portal-system-link-a"></span>
                    <span class="erp-portal-system-link erp-portal-system-link-b"></span>
                    <span class="erp-portal-system-link erp-portal-system-link-c"></span>

                    <span class="erp-portal-system-pulse erp-portal-system-pulse-a" data-portal-pulse></span>
                    <span class="erp-portal-system-pulse erp-portal-system-pulse-b" data-portal-pulse></span>
                    <span class="erp-portal-system-pulse erp-portal-system-pulse-c" data-portal-pulse></span>

                    <div class="erp-portal-system-core">
                        <x-brand.mark size="md" />
                        <span>One secure workspace</span>
                    </div>

                    <div class="erp-portal-system-node erp-portal-system-node-people" data-portal-system-node>
                        <span>People</span>
                        <small>Records &amp; self-service</small>
                    </div>
                    <div class="erp-portal-system-node erp-portal-system-node-time" data-portal-system-node>
                        <span>Time &amp; leave</span>
                        <small>Schedules, DTR &amp; requests</small>
                    </div>
                    <div class="erp-portal-system-node erp-portal-system-node-payroll" data-portal-system-node>
                        <span>Payroll</span>
                        <small>Processing &amp; payslips</small>
                    </div>
                </div>
            </div>

            <div class="erp-portal-context" aria-label="Portal access overview">
                <article>
                    <h2>Employee self-service</h2>
                    <p>Review your profile, DTR, schedule, leave, training, and payslips.</p>
                </article>
                <article>
                    <h2>HR &amp; Accounting</h2>
                    <p>Continue to the workforce and payroll tools assigned to your role.</p>
                </article>
            </div>

            <p class="erp-portal-security">Authorized access only. Activity may be recorded to protect workforce and payroll information.</p>
        </section>

        <section id="signin" class="erp-portal-signin" aria-labelledby="signin-title" data-portal-signin>
            <div class="erp-portal-signin-header">
                <h2 id="signin-title">Sign in to continue</h2>
                <span>Use the credentials issued to you.</span>
            </div>

            @if ($errors->any())
                <div role="alert" class="erp-login-alert" tabindex="-1" data-login-alert>
                    <strong>We couldn't sign you in.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="erp-portal-form" data-login-form>
                @csrf

                <div class="erp-portal-field">
                    <label for="emp_id">Employee ID</label>
                    <input
                        id="emp_id"
                        name="emp_id"
                        value="{{ old('emp_id') }}"
                        autocomplete="username"
                        autofocus
                        required
                        aria-describedby="emp-id-help"
                        @error('emp_id') aria-invalid="true" @enderror
                        placeholder="Enter your employee ID"
                    >
                    <small id="emp-id-help">Use the ID provided by Human Resources.</small>
                </div>

                <div class="erp-portal-field">
                    <label for="password">Password</label>
                    <div class="erp-portal-password">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                        >
                        <button type="button" data-password-toggle aria-label="Show password" aria-controls="password">Show</button>
                    </div>
                </div>

                <label class="erp-portal-remember">
                    <input name="remember" type="checkbox" value="1">
                    <span>Keep me signed in on this device</span>
                </label>

                <button type="submit" class="erp-portal-submit"><span>Sign in</span></button>
            </form>

            <div class="erp-portal-help">
                <strong>Having trouble signing in?</strong>
                <p>Contact your HRIS administrator to verify your account access.</p>
            </div>
        </section>
    </main>

    <footer class="erp-portal-footer">
        <p>&copy; {{ date('Y') }} MMMHMC</p>
        <p>Human Resources Information System &amp; Payroll</p>
    </footer>

    <script>
        const passwordButton = document.querySelector('[data-password-toggle]');
        passwordButton?.addEventListener('click', () => {
            const input = document.getElementById(passwordButton.getAttribute('aria-controls'));
            if (!input) return;
            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            passwordButton.textContent = willShow ? 'Hide' : 'Show';
            passwordButton.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
        });

        document.querySelector('[data-login-form]')?.addEventListener('submit', (event) => {
            if (!event.currentTarget.checkValidity()) return;
            const button = event.currentTarget.querySelector('[type="submit"]');
            button.disabled = true;
            button.querySelector('span').textContent = 'Signing in...';
            button.setAttribute('aria-busy', 'true');
        });

        document.querySelector('[data-login-alert]')?.focus({ preventScroll: true });
    </script>
</body>
</html>
