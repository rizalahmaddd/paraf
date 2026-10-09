@php
    $theme = request()->cookie('theme');
    if (! in_array($theme, ['light', 'dark'])) {
        $theme = 'dark';
    }
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full {{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Support\Branding::appName() }}</title>
        @if ($brandLogoUrl = \App\Support\Branding::logoUrl())
            <link rel="icon" href="{{ $brandLogoUrl }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Caveat:wght@600&family=Dancing+Script:wght@600&family=Great+Vibes&family=Sacramento&display=swap" rel="stylesheet">

        <script>
            (function() {
                try {
                    var theme = localStorage.getItem('theme');
                    if (theme === 'light' || theme === 'dark') {
                        document.documentElement.classList.remove('light', 'dark');
                        document.documentElement.classList.add(theme);
                    }
                } catch (e) {}
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/signer.js'])
    </head>
    <body class="min-h-full font-sans antialiased bg-slate-950 text-slate-100">
        <header class="sticky top-0 z-30 border-b border-slate-800/80 bg-slate-900/90 backdrop-blur-md pt-[env(safe-area-inset-top)]">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 h-14 px-4">
                <div class="flex items-center gap-2.5 min-w-0">
                    <x-brand-mark size="w-7 h-7" padding="p-1.5" radius="rounded-lg" />
                    <div class="min-w-0">
                        @isset($header)
                            {{ $header }}
                        @else
                            <p class="text-sm font-bold text-slate-100 truncate">{{ \App\Support\Branding::appName() }}</p>
                        @endisset
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    {{ $actions ?? '' }}
                    <x-theme-toggle />
                </div>
            </div>
            {{ $subheader ?? '' }}
        </header>

        <main class="max-w-5xl mx-auto px-4 py-5 sm:py-8 pb-[calc(6rem+env(safe-area-inset-bottom))]">
            {{ $slot }}
        </main>

        <x-toast-container />
        @livewireScripts
    </body>
</html>
