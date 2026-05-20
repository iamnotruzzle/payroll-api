<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Payroll Scheduler</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="erp-body min-h-screen bg-[#f5f5f9] text-[#2f3349] antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-8">
        <section class="w-full max-w-[420px] overflow-hidden rounded-lg border border-[#e4e6ef] bg-white shadow-sm shadow-slate-900/5">
            <div class="border-b border-[#eceef6] px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-md bg-[#696cff] text-sm font-bold text-white shadow-sm shadow-[#696cff]/25">PM</div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase text-[#8a8d93]">MMMHMC ERP</p>
                        <h1 class="text-lg font-semibold text-[#2f3349]">Payroll Scheduler</h1>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4 px-5 py-5">
                @csrf

                <div>
                    <label for="emp_id" class="text-xs font-semibold text-[#566a7f]">Employee ID</label>
                    <input
                        id="emp_id"
                        name="emp_id"
                        value="{{ old('emp_id') }}"
                        autocomplete="username"
                        autofocus
                        class="mt-1 w-full rounded-md border border-[#d9dee8] px-3 py-2 text-sm outline-none focus:border-[#696cff]"
                    >
                    @error('emp_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="text-xs font-semibold text-[#566a7f]">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-md border border-[#d9dee8] px-3 py-2 text-sm outline-none focus:border-[#696cff]"
                    >
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-[#566a7f]">
                        <input name="remember" type="checkbox" value="1" class="rounded border-[#d9dee8] text-[#696cff]">
                        Keep signed in
                    </label>
                </div>

                <button class="w-full rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-[#696cff]/25 hover:bg-[#5f61e6]">
                    Sign In
                </button>
            </form>
        </section>
    </main>
</body>
</html>
