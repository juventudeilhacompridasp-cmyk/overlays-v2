<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Tema claro/escuro: aplicado antes da pintura para não piscar. Telas de vídeo/OBS (Overlay, Public) ficam sempre no visual próprio. -->
        <script>
            (function () {
                try {
                    if (/^(Overlay|Public|Embed)\//.test(@json($page['component']))) return;
                    var theme = localStorage.getItem('theme') || 'system';
                    var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        @if (str_starts_with($page['component'], 'Panel/'))
            <!-- App do mesário (PWA): instalável a partir do painel. -->
            <link rel="manifest" href="/mesario.webmanifest">
            <meta name="theme-color" content="#059669">
            <link rel="apple-touch-icon" href="/icons/icon-192.png">
        @endif
        @if (str_starts_with($page['component'], 'Portal/') || str_starts_with($page['component'], 'Public/'))
            <link rel="manifest" href="/portal.webmanifest">
            <meta name="theme-color" content="#059669">
            <link rel="apple-touch-icon" href="/icons/icon-192.png">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
