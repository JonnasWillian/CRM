<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        {{-- Telas do app não vão para o índice: o site público (Blade) é a porta de entrada. --}}
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Tema antes da primeira pintura (mesma regra de useTema.js): sem piscar claro no tema escuro. --}}
        <script>
            (function () {
                var t = 'sistema';
                try { t = localStorage.getItem('ui.tema') || 'sistema'; } catch (e) {}
                if (t !== 'claro' && t !== 'escuro') t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro';
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>

        <title inertia>{{ config('app.name', 'UserFlow') }}</title>

        {{-- Fontes: self-host via @fontsource-variable, importadas em resources/css/app.css. --}}

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
