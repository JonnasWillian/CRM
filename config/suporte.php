<?php

/*
 * Assistente de ajuda e feedback (botão flutuante do app). O destino dos
 * chamados é fixo aqui: nunca vem do navegador.
 */
return [
    // Caixa que recebe os chamados em que a pessoa pediu o suporte.
    'email' => env('SUPORTE_EMAIL', 'contato@devscody.com'),

    // Como o chamado chega à caixa: `mail` (SMTP + fila do sistema) ou `formsubmit`
    // (formsubmit.co entrega, sem SMTP nem fila; os dados passam por esse serviço).
    'canal' => env('SUPORTE_CANAL', 'mail'),

    // Opcional: o código que o FormSubmit dá depois da ativação, para usar no lugar do e-mail na URL.
    'formsubmit_destino' => env('SUPORTE_FORMSUBMIT_DESTINO'),

    // Teto de envios por pessoa em 24 h (além do throttle por minuto da rota).
    'maximo_por_dia' => (int) env('SUPORTE_MAXIMO_POR_DIA', 15),

    'tipos' => [
        'problema' => 'Algo não funciona',
        'duvida' => 'Dúvida de uso',
        'sugestao' => 'Sugestão de melhoria',
        'cobranca' => 'Cobrança e assinatura',
        'elogio' => 'Elogio',
    ],

    'areas' => [
        'dashboard' => 'Dashboard',
        'leads' => 'Leads',
        'pipeline' => 'Pipeline',
        'tarefas' => 'Tarefas',
        'relatorios' => 'Relatórios',
        'importacao' => 'Importação de planilha',
        'equipe' => 'Equipe e convites',
        'assinatura' => 'Assinatura e pagamento',
        'acesso' => 'Login e conta',
        'outro' => 'Outro',
    ],

    'impactos' => [
        'bloqueia' => 'Não consigo trabalhar',
        'atrapalha' => 'Atrapalha, mas consigo trabalhar',
        'detalhe' => 'É um detalhe',
    ],
];
