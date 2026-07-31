<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payroll Generation' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="erp-body bg-[#f5f5f9] text-[#2f3349] antialiased">
    <main class="erp-content min-h-screen w-full">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
