<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>{{ $titulo }}</title>
        <meta name="description" content="{{ $descricao }}">
        <link rel="canonical" href="{{ $url($caminho) }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate" hreflang="pt-BR" href="{{ $url($caminho) }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $nome }}">
        <meta property="og:locale" content="pt_BR">
        <meta property="og:title" content="{{ $titulo }}">
        <meta property="og:description" content="{{ $descricao }}">
        <meta property="og:url" content="{{ $url($caminho) }}">
        <meta property="og:image" content="{{ $url('/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $titulo }}">
        <meta name="twitter:description" content="{{ $descricao }}">
        <meta name="twitter:image" content="{{ $url('/og.png') }}">

        @foreach ($jsonLd as $bloco)
            <script type="application/ld+json">{!! json_encode($bloco, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endforeach

        {{-- Mesmo tema do app (claro/escuro/sistema), aplicado antes da primeira pintura. --}}
        <script>
            (function () {
                var t = 'sistema';
                try { t = localStorage.getItem('ui.tema') || 'sistema'; } catch (e) {}
                if (t !== 'claro' && t !== 'escuro') t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro';
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>

        @vite(['resources/css/app.css'])

        @if (config('comercial.analytics.ga4'))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('comercial.analytics.ga4') }}"></script>
            <script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{{ config('comercial.analytics.ga4') }}', { anonymize_ip: true });</script>
        @endif
    </head>
    <body class="min-h-dvh bg-bg-0 font-sans text-fg-0 antialiased">
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-bg-1 focus:px-3 focus:py-2 focus:shadow-2">Ir para o conteúdo</a>

        <header class="sticky top-0 z-40 border-b border-line bg-bg-1/90 backdrop-blur-sm">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 md:px-6">
                <a href="/" class="font-display text-xl font-bold tracking-tight text-fg-0" aria-label="{{ $nome }}, página inicial">{{ $nome }}<span class="text-primary">.</span></a>
                <nav aria-label="Site" class="hidden items-center gap-6 text-sm font-medium text-fg-1 md:flex">
                    <a href="/#funcionalidades" class="hover:text-fg-0">Funcionalidades</a>
                    <a href="/#recorrencia" class="hover:text-fg-0">Receita recorrente</a>
                    <a href="/precos" class="hover:text-fg-0 @if ($caminho === '/precos') text-fg-0 @endif">Preços</a>
                    <a href="/#perguntas" class="hover:text-fg-0">Perguntas</a>
                </nav>
                <div class="flex items-center gap-2">
                    <a href="/login" class="hidden h-9 items-center rounded px-3 text-sm font-medium text-fg-1 hover:bg-bg-2 hover:text-fg-0 sm:inline-flex">Entrar</a>
                    <a href="/register" class="inline-flex h-9 items-center rounded bg-primary px-3.5 text-sm font-semibold text-primary-fg shadow-1 hover:bg-primary-hover">Começar grátis</a>
                </div>
            </div>
        </header>

        <main id="conteudo">
            @yield('conteudo')
        </main>

        <footer class="border-t border-line bg-bg-1">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 md:grid-cols-4 md:px-6">
                <div class="md:col-span-2">
                    <p class="font-display text-lg font-bold tracking-tight">{{ $nome }}<span class="text-primary">.</span></p>
                    <p class="mt-2 max-w-sm text-sm text-fg-2">{{ config('comercial.slogan') }}</p>
                    <p class="mt-4 text-sm text-fg-2">Feito no Brasil, em português, com dados hospedados por empresa.</p>
                </div>
                <nav aria-label="Produto" class="text-sm">
                    <p class="mb-2 font-semibold text-fg-0">Produto</p>
                    <ul class="space-y-1.5 text-fg-1">
                        <li><a href="/#funcionalidades" class="hover:text-fg-0">Funcionalidades</a></li>
                        <li><a href="/precos" class="hover:text-fg-0">Preços</a></li>
                        <li><a href="/register" class="hover:text-fg-0">Criar conta</a></li>
                        <li><a href="/login" class="hover:text-fg-0">Entrar</a></li>
                    </ul>
                </nav>
                <nav aria-label="Empresa" class="text-sm">
                    <p class="mb-2 font-semibold text-fg-0">Empresa</p>
                    <ul class="space-y-1.5 text-fg-1">
                        <li><a href="mailto:{{ $contato['email'] }}" class="hover:text-fg-0">{{ $contato['email'] }}</a></li>
                        @if ($contato['whatsapp'])
                            <li><a href="https://wa.me/{{ $contato['whatsapp'] }}" rel="noopener" class="hover:text-fg-0">WhatsApp comercial</a></li>
                        @endif
                        <li><a href="/termos" class="hover:text-fg-0">Termos de uso</a></li>
                        <li><a href="/privacidade" class="hover:text-fg-0">Política de privacidade</a></li>
                    </ul>
                </nav>
            </div>
            <div class="border-t border-line">
                <p class="mx-auto max-w-6xl px-4 py-4 text-xs text-fg-2 md:px-6">© {{ date('Y') }} {{ $empresa['razao_social'] }}@if ($empresa['cnpj']) · CNPJ {{ $empresa['cnpj'] }}@endif. Todos os direitos reservados.</p>
            </div>
        </footer>
    </body>
</html>
