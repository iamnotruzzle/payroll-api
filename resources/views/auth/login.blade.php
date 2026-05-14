<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Payroll Scheduler</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MMMHMC</p>
                <h1 class="mt-1 text-2xl font-semibold">Payroll Scheduler</h1>
                <p class="mt-2 text-sm text-slate-600">Sign in with your employee ID.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="emp_id" class="text-sm font-medium">Employee ID</label>
                    <input
                        id="emp_id"
                        name="emp_id"
                        value="{{ old('emp_id') }}"
                        autocomplete="username"
                        autofocus
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none ring-0 focus:border-blue-500"
                    >
                    @error('emp_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="text-sm font-medium">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm outline-none ring-0 focus:border-blue-500"
                    >
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input name="remember" type="checkbox" value="1" class="rounded border-slate-300">
                    Keep me signed in
                </label>

                <button class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                    Sign In
                </button>
            </form>
        </section>
    </main>
</body>
</html>
