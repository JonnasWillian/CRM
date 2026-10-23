{{-- $compacto: na página inicial mostra menos itens; em /precos, tudo. --}}
<div class="mt-8 grid gap-4 md:grid-cols-3">
    @foreach ($planos as $plano)
        <article class="relative flex flex-col rounded-xl border bg-bg-1 p-6 {{ $plano['destaque'] ? 'border-primary shadow-2' : 'border-line shadow-1' }}">
            @if ($plano['destaque'])
                <span class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-fg">Mais escolhido</span>
            @endif
            <h3 class="font-display text-xl font-semibold">{{ $plano['nome'] }}</h3>
            <p class="mt-1 text-sm text-fg-2">{{ $plano['descricao'] }}</p>
            <p class="num mt-5"><span class="font-display text-4xl font-bold">R$ {{ $plano['preco_anual'] }}</span><span class="text-sm text-fg-2"> /usuário/mês</span></p>
            <p class="text-xs text-fg-2">no plano anual · R$ {{ $plano['preco_mensal'] }} no mensal</p>
            @if (!empty($plano['limite']))<p class="mt-3 text-sm font-medium text-fg-1">{{ $plano['limite'] }}</p>@endif
            <ul class="mt-4 flex-1 space-y-2 text-sm text-fg-0">
                @foreach (($compacto ?? false) ? array_slice($plano['inclui'], 0, 3) : $plano['inclui'] as $item)
                    <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>{{ $item }}</li>
                @endforeach
            </ul>
            <a href="{{ $url('/register') }}?plano={{ $plano['chave'] }}" class="mt-6 inline-flex h-10 items-center justify-center rounded text-md font-semibold {{ $plano['destaque'] ? 'bg-primary text-primary-fg hover:bg-primary-hover' : 'border border-line bg-bg-1 text-fg-0 hover:bg-bg-2' }}">Começar grátis</a>
        </article>
    @endforeach
</div>
<p class="mt-4 text-sm text-fg-2">Todos os planos têm {{ $diasDeTeste }} dias grátis com todos os recursos. Preços em reais, por usuário ativo; impostos inclusos.</p>
