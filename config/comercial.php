<?php

/*
 * Tudo o que o site público diz sobre o produto, em um lugar só: nome (vem
 * de APP_NAME), slogan, planos, perguntas frequentes e dados da empresa para
 * os termos. Mudar o preço ou o nome é editar aqui — nenhuma página tem
 * número cravado.
 *
 * Os limites (`limites`) são aplicados por Services\Assinaturas\Assinaturas.
 * A cobrança em si ainda não existe: ver docs/comercial/analise-comercial-e-seo.md.
 */
return [
    'slogan' => 'O CRM de vendas que mostra o que você já fatura e o que ainda vai entrar.',

    // ≤ 155 caracteres: é o texto que aparece no Google.
    'descricao' => 'CRM de vendas em português para equipes pequenas: pipeline, tarefas, equipe, importação de planilhas e contratos recorrentes com previsão de receita.',

    'publico' => 'agências, consultorias, estúdios e prestadores de serviço com equipes de 2 a 30 vendedores',

    'dias_de_teste' => 14,

    // Desligue para não bloquear escrita nem aplicar limites (ex.: na migração de clientes). A suíte de testes roda desligada.
    'exigir_assinatura' => (bool) env('COMERCIAL_EXIGIR_ASSINATURA', true),

    'contato' => [
        'email' => env('COMERCIAL_EMAIL', env('MAIL_FROM_ADDRESS', 'contato@example.com')),
        'whatsapp' => env('COMERCIAL_WHATSAPP'), // só dígitos com DDI, ex.: 5511999998888
    ],

    // Dados que aparecem nos Termos de uso e na Política de privacidade.
    'empresa' => [
        'razao_social' => env('COMERCIAL_RAZAO_SOCIAL', env('APP_NAME', 'UserFlow')),
        'cnpj' => env('COMERCIAL_CNPJ'),
        'cidade' => env('COMERCIAL_CIDADE', 'Brasil'),
        'dpo_email' => env('COMERCIAL_DPO_EMAIL', env('COMERCIAL_EMAIL', env('MAIL_FROM_ADDRESS', 'contato@example.com'))),
    ],

    'analytics' => [
        'ga4' => env('COMERCIAL_GA4_ID'), // ex.: G-XXXXXXX; vazio = sem rastreio
    ],

    // Por usuário por mês. `preco_anual` é o valor mensal equivalente no plano anual.
    'planos' => [
        [
            'chave' => 'inicial',
            'nome' => 'Inicial',
            'preco_mensal' => 49,
            'preco_anual' => 39,
            'descricao' => 'Para quem está tirando a carteira da planilha.',
            'limite' => 'Até 3 usuários · 1 funil · 2.000 leads',
            'limites' => ['usuarios' => 3, 'funis' => 1, 'leads' => 2000], // null = sem limite
            'inclui' => [
                'Pipeline com arrastar e soltar',
                'Leads com valor, probabilidade e previsão',
                'Tarefas, modelos de follow-up e histórico completo',
                'Importação e exportação de planilhas (CSV)',
                'Dashboard com faturamento e projeção',
            ],
            'destaque' => false,
        ],
        [
            'chave' => 'profissional',
            'nome' => 'Profissional',
            'preco_mensal' => 89,
            'preco_anual' => 69,
            'descricao' => 'Para equipes que precisam de previsão e controle.',
            'limite' => 'Usuários, funis e leads ilimitados',
            'limites' => ['usuarios' => null, 'funis' => null, 'leads' => null],
            'inclui' => [
                'Tudo do Inicial',
                'Equipe com papéis, convites e transferência de carteira',
                'Contratos recorrentes: MRR, renovações e aditivos',
                'Relatórios por vendedor e por motivo de perda',
                'Vários funis (vendas, pós-venda, parcerias)',
            ],
            'destaque' => true,
        ],
        [
            'chave' => 'empresa',
            'nome' => 'Empresa',
            'preco_mensal' => 139,
            'preco_anual' => 109,
            'descricao' => 'Para operações com várias equipes e exigências de conformidade.',
            'limite' => 'A partir de 5 usuários',
            'limites' => ['usuarios' => null, 'funis' => null, 'leads' => null],
            'inclui' => [
                'Tudo do Profissional',
                'Importação assistida da base atual',
                'Onboarding da equipe em videochamada',
                'Suporte prioritário em horário comercial',
                'Acordo de tratamento de dados (LGPD) assinado',
            ],
            'destaque' => false,
        ],
    ],

    'faq' => [
        ['pergunta' => 'Preciso de cartão para testar?', 'resposta' => 'Não. Você cria a conta, convida a equipe e usa todos os recursos por :dias dias. Se gostar, escolhe o plano; se não, não paga nada.'],
        ['pergunta' => 'Como é a cobrança?', 'resposta' => 'Por usuário ativo, por mês, em reais. No plano anual você paga 12 meses com desconto. Usuários desativados não contam.'],
        ['pergunta' => 'Consigo trazer minha planilha ou os dados do CRM antigo?', 'resposta' => 'Sim. A importação lê CSV exportado do Excel, do Google Planilhas ou de outro CRM, sugere o mapeamento das colunas, avisa linha a linha o que não entrou e evita duplicados por e-mail.'],
        ['pergunta' => 'Meus dados ficam presos?', 'resposta' => 'Não. Você exporta seus leads em CSV a qualquer momento, com os mesmos cabeçalhos que a importação aceita. A exclusão da conta apaga seu acesso e segue a LGPD.'],
        ['pergunta' => 'O que é o controle de contratos recorrentes?', 'resposta' => 'Para quem vende serviço mensal: cada projeto recorrente tem valor mensal, prazo e renovações. O sistema conta o que já foi faturado até o mês atual, o que ainda vai entrar e separa isso do que está em negociação.'],
        ['pergunta' => 'Funciona com WhatsApp?', 'resposta' => 'Cada lead tem o botão de abrir a conversa no WhatsApp com um clique e o telefone já formatado. A integração oficial, com as mensagens dentro do CRM, está no nosso roteiro.'],
        ['pergunta' => 'Outras empresas veem meus dados?', 'resposta' => 'Nunca. Cada empresa tem seu próprio espaço isolado; dentro dele, o vendedor vê só a própria carteira e o gestor vê a equipe toda.'],
    ],
];
