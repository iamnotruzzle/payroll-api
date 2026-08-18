<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payroll Generation' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <script>
        (() => {
            const saved = localStorage.getItem('erp-theme');
            document.documentElement.dataset.theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="erp-body erp-payroll-workspace antialiased">
    <main class="erp-content erp-payroll-workspace-frame min-h-screen w-full">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
