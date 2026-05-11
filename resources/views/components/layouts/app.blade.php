<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payroll Scheduler' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-950 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">MMMHMC</p>
                    <h1 class="text-lg font-semibold">Payroll Scheduler</h1>
                </div>
                <nav class="flex gap-2 text-sm">
                    <a class="rounded-md px-3 py-2 hover:bg-slate-100" href="{{ route('schedule.dashboard') }}">Schedules</a>
                    <a class="rounded-md px-3 py-2 hover:bg-slate-100" href="{{ route('schedule.shift-codes') }}">Shift Codes</a>
                    <a class="rounded-md px-3 py-2 hover:bg-slate-100" href="/api/employees">Employees API</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
