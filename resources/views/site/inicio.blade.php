@extends('site.layout')

@section('conteudo')
    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pb-12 pt-14 md:px-6 md:pb-20 md:pt-24">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <p class="mb-3 inline-flex items-center gap-2 rounded-full border border-line bg-bg-1 px-3 py-1 text-xs font-medium text-fg-1">
                    <span class="h-2 w-2 rounded-full bg-ok" aria-hidden="true"></span> Para {{ config('comercial.publico') }}
                </p>
                <h1 class="font-display text-4xl font-bold leading-tight tracking-tight text-fg-0 md:text-5xl">
                    O CRM de vendas que mostra o que você <span class="text-primary">já fatura</span> e o que ainda vai entrar.
                </h1>
                <p class="mt-5 max-w-xl text-lg text-fg-1">
                    Pipeline, tarefas e equipe num só lugar, em português. E uma conta que os outros CRMs não fazem: contratos mensais com renovações, faturamento até o mês atual e a projeção do que falta.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ $url('/register') }}" class="inline-flex h-11 items-center rounded bg-primary px-5 text-md font-semibold text-primary-fg shadow-1 hover:bg-primary-hover">Começar grátis por {{ $diasDeTeste }} dias</a>
                    <a href="{{ $url('/precos') }}" class="inline-flex h-11 items-center rounded border border-line bg-bg-1 px-5 text-md font-medium text-fg-0 hover:bg-bg-2">Ver preços</a>
                </div>
                <p class="mt-3 text-sm text-fg-2">Sem cartão. Importe sua planilha em cinco minutos.</p>
            </div>

            {{-- Ilustração: um pipeline desenhado em HTML (sem imagem para carregar). --}}
            <div class="rounded-xl border border-line bg-bg-1 p-3 shadow-3" aria-hidden="true">
                <div class="mb-3 flex items-center justify-between px-1">
                    <div class="flex items-center gap-2 text-sm font-semibold">Pipeline <span class="rounded-full bg-bg-2 px-2 py-0.5 text-xs font-medium text-fg-2">48 leads · R$ 210 mil</span></div>
                    <div class="h-7 w-24 rounded bg-primary/90"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ([['Em captação', '#2b6cb0', ['Ana Souza · R$ 8.000', 'Loja Horizonte · R$ 3.200']], ['Proposta enviada', '#9c6612', ['Clínica Vitale · R$ 12.000', 'Nexa Studio · R$ 4.800']], ['Ganho', '#1a7544', ['Agência Prisma · R$ 2.400/mês']]] as [$coluna, $cor, $cards])
                        <div class="rounded-lg border border-line bg-bg-2/60 p-2" style="border-top: 3px solid {{ $cor }}">
                            <p class="mb-2 truncate px-1 text-xs font-semibold text-fg-0">{{ $coluna }}</p>
                            @foreach ($cards as $card)
                                <div class="mb-2 rounded border border-line bg-bg-1 p-2 text-xs shadow-1">
                                    <p class="truncate font-medium text-fg-0">{{ explode(' · ', $card)[0] }}</p>
                                    <p class="num mt-0.5 text-fg-2">{{ explode(' · ', $card)[1] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-ok-line bg-bg-1 p-3"><p class="text-xs text-fg-2">Faturando este mês</p><p class="num font-display text-lg font-semibold text-ok">R$ 18.400</p></div>
                    <div class="rounded-lg border border-info-line bg-bg-1 p-3"><p class="text-xs text-fg-2">Em projeção</p><p class="num font-display text-lg font-semibold text-info">R$ 96.500</p></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Dor --}}
    <section class="border-y border-line bg-bg-1">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 py-12 md:grid-cols-3 md:px-6">
            @foreach ([
                ['A planilha não avisa', 'Follow-up esquecido, proposta parada há 20 dias e ninguém viu. O CRM mostra quem está sem contato e o que vence hoje.'],
                ['O CRM gringo é em dólar', 'E vem com cem recursos que sua equipe nunca abre. Aqui o preço é em reais e a tela tem só o que o vendedor usa.'],
                ['Receita recorrente mal contada', 'Contrato mensal renovado três vezes vira um projeto de valor único no CRM comum. Aqui ele é contado mês a mês.'],
            ] as [$t, $d])
                <div><h2 class="font-display text-lg font-semibold text-fg-0">{{ $t }}</h2><p class="mt-2 text-md text-fg-1">{{ $d }}</p></div>
            @endforeach
        </div>
    </section>

    {{-- Funcionalidades --}}
    <section id="funcionalidades" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 md:px-6">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-wider text-primary">Funcionalidades</p>
            <h2 class="mt-2 font-display text-3xl font-bold tracking-tight">Tudo o que uma equipe de vendas usa todo dia. Nada do que não usa.</h2>
        </div>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Pipeline visual', 'Arraste o lead entre estágios, marque ganho com o valor fechado e perdido com o motivo. Vários funis se você tem mais de um processo.'],
                ['Leads com valor e previsão', 'Valor estimado, probabilidade e data prevista em cada lead. O dashboard soma o ponderado e avisa quem está parado há mais de 14 dias.'],
                ['Tarefas e follow-ups', 'Tarefas por lead, modelos prontos ("ligar em 3 dias", "enviar proposta") e a lista do dia: atrasadas, hoje, próximos 7 dias.'],
                ['Equipe e carteira', 'Convide por e-mail, defina admin, gestor ou vendedor, transfira leads entre pessoas e desative quem saiu sem perder histórico.'],
                ['Importar e exportar', 'Traga a planilha com mapeamento de colunas e aviso linha a linha. Exporte quando quiser, no mesmo formato.'],
                ['Relatórios que respondem', 'Quanto cada vendedor vendeu, por que estamos perdendo, ganhos mês a mês e a previsão ponderada. Por período e por funil.'],
            ] as [$t, $d])
                <article class="rounded-lg border border-line bg-bg-1 p-5 shadow-1">
                    <h3 class="font-display text-lg font-semibold text-fg-0">{{ $t }}</h3>
                    <p class="mt-2 text-md text-fg-1">{{ $d }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Recorrência --}}
    <section id="recorrencia" class="scroll-mt-20 border-y border-line bg-bg-1">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:px-6 lg:grid-cols-2">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-primary">Receita recorrente</p>
                <h2 class="mt-2 font-display text-3xl font-bold tracking-tight">Vende serviço mensal? Este é o único CRM que conta certo.</h2>
                <p class="mt-4 text-md text-fg-1">Cada contrato tem valor mensal, prazo e início. O sistema conta os meses até hoje, soma o que já foi faturado, separa do que ainda vai entrar e avisa quando o contrato está para vencer. Renovação e aditivo entram no mesmo histórico, com o novo valor a partir da data certa.</p>
                <ul class="mt-6 space-y-2 text-md text-fg-0">
                    @foreach (['MRR do mês e faturamento acumulado por contrato', 'Renovações e aditivos com reajuste de valor', 'Projeção: o que falta dos contratos + propostas + leads ponderados', 'Alerta de contratos que vencem em 30 dias'] as $item)
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-ok" aria-hidden="true"></span>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-xl border border-line bg-bg-0 p-4 shadow-2" aria-hidden="true">
                <p class="text-sm font-semibold">Gestão de tráfego · Agência Prisma</p>
                <p class="mt-1 text-xs text-fg-2">Vigente · 15/08/2026 → 14/08/2028</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-bg-2"><div class="h-full w-[13%] rounded-full bg-primary"></div></div>
                <p class="mt-1 text-xs text-fg-2">3 de 24 meses</p>
                <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    @foreach ([['Valor deste mês', 'R$ 1.500,00'], ['Contabilizado', 'R$ 4.500,00'], ['A realizar', 'R$ 35.100,00'], ['Total do contrato', 'R$ 39.600,00']] as [$k, $v])
                        <div class="rounded border border-line bg-bg-1 p-2"><dt class="text-fg-2">{{ $k }}</dt><dd class="num mt-0.5 text-md font-semibold text-fg-0">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                <table class="mt-4 w-full text-xs">
                    <tbody class="divide-y divide-line">
                        <tr><td class="py-1.5">Contrato inicial</td><td class="num py-1.5 text-right text-fg-2">12 × R$ 1.500</td></tr>
                        <tr><td class="py-1.5">Renovação · reajuste anual</td><td class="num py-1.5 text-right text-fg-2">12 × R$ 1.800</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Confiança --}}
    <section class="mx-auto max-w-6xl px-4 py-16 md:px-6">
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ([
                ['Seus dados são seus', 'Exportação completa em CSV a qualquer momento. Sem fidelidade, sem multa de saída.'],
                ['Isolado por empresa', 'Cada empresa tem seu espaço separado. Vendedor vê só a própria carteira; gestor vê a equipe.'],
                ['LGPD desde o cadastro', 'Política de privacidade clara, canal para direitos do titular e exclusão de conta com um clique.'],
            ] as [$t, $d])
                <div class="rounded-lg border border-line bg-bg-1 p-5"><h2 class="font-display text-lg font-semibold">{{ $t }}</h2><p class="mt-2 text-md text-fg-1">{{ $d }}</p></div>
            @endforeach
        </div>
    </section>

    {{-- Preços resumidos --}}
    <section id="precos" class="border-y border-line bg-bg-1">
        <div class="mx-auto max-w-6xl px-4 py-16 md:px-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><p class="text-sm font-semibold uppercase tracking-wider text-primary">Preços</p><h2 class="mt-2 font-display text-3xl font-bold tracking-tight">Por usuário, em reais, sem surpresa.</h2></div>
                <a href="{{ $url('/precos') }}" class="text-md font-medium text-primary hover:underline">Comparar os planos →</a>
            </div>
            @include('site._planos', ['compacto' => true])
        </div>
    </section>

    {{-- FAQ --}}
    <section id="perguntas" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-16 md:px-6">
        <h2 class="font-display text-3xl font-bold tracking-tight">Perguntas frequentes</h2>
        <div class="mt-6 divide-y divide-line rounded-lg border border-line bg-bg-1">
            @foreach ($faq as $item)
                <details class="group p-4">
                    <summary class="cursor-pointer list-none font-medium text-fg-0 marker:content-none">{{ $item['pergunta'] }}</summary>
                    <p class="mt-2 text-md text-fg-1">{{ $item['resposta'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA final --}}
    <section class="bg-primary">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6 px-4 py-14 md:px-6">
            <div>
                <h2 class="font-display text-3xl font-bold tracking-tight text-primary-fg">Comece hoje com a sua planilha.</h2>
                <p class="mt-2 text-md text-primary-fg/85">{{ $diasDeTeste }} dias grátis, sem cartão. Importe os leads, convide a equipe e veja o pipeline funcionando ainda hoje.</p>
            </div>
            <a href="{{ $url('/register') }}" class="inline-flex h-11 items-center rounded bg-bg-1 px-5 text-md font-semibold text-primary shadow-2 hover:bg-bg-2">Criar conta grátis</a>
        </div>
    </section>
@endsection
