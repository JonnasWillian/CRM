@extends('site.layout')

@section('conteudo')
    <section class="mx-auto max-w-6xl px-4 py-14 md:px-6">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-wider text-primary">Preços</p>
            <h1 class="mt-2 font-display text-4xl font-bold tracking-tight">Planos por usuário, em reais.</h1>
            <p class="mt-3 text-lg text-fg-1">Comece grátis por {{ $diasDeTeste }} dias com todos os recursos. Depois, escolha o plano do tamanho da equipe.</p>
        </div>
        @include('site._planos', ['compacto' => false])
    </section>

    <section class="border-t border-line bg-bg-1">
        <div class="mx-auto max-w-6xl px-4 py-14 md:px-6">
            <h2 class="font-display text-2xl font-bold tracking-tight">O que está em todos os planos</h2>
            <div class="mt-6 grid gap-x-8 gap-y-3 text-md text-fg-0 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['Pipeline com arrastar e soltar', 'Histórico completo de cada lead', 'Tarefas com modelos de follow-up', 'Botão de WhatsApp em cada lead', 'Importação e exportação em CSV', 'Lixeira com restauração', 'Dashboard com período e vendedor', 'Tema claro e escuro', 'Dados isolados por empresa', 'Exclusão de conta conforme a LGPD', 'Acesso pelo celular (navegador)', 'Suporte por e-mail'] as $item)
                    <p class="flex items-start gap-2"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-ok" aria-hidden="true"></span>{{ $item }}</p>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 py-14 md:px-6">
        <h2 class="font-display text-2xl font-bold tracking-tight">Perguntas sobre cobrança</h2>
        <div class="mt-6 divide-y divide-line rounded-lg border border-line bg-bg-1">
            @foreach (array_slice($faq, 0, 4) as $item)
                <details class="group p-4"><summary class="cursor-pointer list-none font-medium">{{ $item['pergunta'] }}</summary><p class="mt-2 text-md text-fg-1">{{ $item['resposta'] }}</p></details>
            @endforeach
        </div>
        <p class="mt-6 text-md text-fg-1">Equipe maior ou pedido especial? <a href="mailto:{{ $contato['email'] }}" class="font-medium text-primary hover:underline">Fale com a gente</a>.</p>
    </section>
@endsection
