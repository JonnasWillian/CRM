<?php

/*
 * Questionário de primeiro acesso: perguntas, opções e o que cada resposta
 * monta na empresa (PersonalizarEmpresa). Os textos vêm da spec
 * docs/superpowers/specs/2026-11-01-onboarding-personalizado-design.md e
 * podem ser ajustados aqui sem mexer em código. As chaves ficam gravadas
 * em perfis_de_empresa.respostas: renomear uma chave quebra as respostas
 * antigas; trocar o rótulo, não.
 */
return [

    'experiencias' => [
        'varios' => 'Já usei vários CRMs e conheço bem',
        'alguns' => 'Já usei um ou outro no dia a dia',
        'nunca' => 'Nunca usei ou uso pouco',
    ],

    'cargos' => [
        'dono' => 'Dono(a) ou sócio(a)',
        'diretor' => 'Diretor(a) comercial',
        'gestor' => 'Gestor(a) de vendas',
        'vendedor' => 'Vendedor(a)',
        'marketing' => 'Marketing',
        'outro' => 'Outro',
    ],

    // Estágios abertos na ordem; `ganho` e `perdido` fecham o funil.
    // `outro` mantém o funil padrão do cadastro (estagios = null).
    'ramos' => [
        'agencia' => [
            'rotulo' => 'Agência (marketing, publicidade, digital)',
            'estagios' => ['Em captação', 'Briefing', 'Proposta enviada', 'Negociação'],
            'ganho' => 'Ganho', 'perdido' => 'Perdido',
            'motivos' => ['Preferiu fazer internamente', 'Escopo fora do que oferecemos'],
        ],
        'consultoria' => [
            'rotulo' => 'Consultoria e assessoria',
            'estagios' => ['Primeiro contato', 'Diagnóstico', 'Proposta enviada', 'Negociação'],
            'ganho' => 'Ganho', 'perdido' => 'Perdido',
            'motivos' => ['Projeto adiado pela diretoria', 'Preferiu resolver internamente'],
        ],
        'software' => [
            'rotulo' => 'Software e tecnologia',
            'estagios' => ['Lead', 'Demonstração', 'Período de teste', 'Proposta'],
            'ganho' => 'Ganho', 'perdido' => 'Perdido',
            'motivos' => ['Faltou funcionalidade', 'Ficou com a ferramenta atual'],
        ],
        'servicos' => [
            'rotulo' => 'Serviços profissionais (contabilidade, advocacia, engenharia)',
            'estagios' => ['Contato', 'Reunião', 'Orçamento enviado', 'Negociação'],
            'ganho' => 'Ganho', 'perdido' => 'Perdido',
            'motivos' => ['Prazo não atendeu', 'Preferiu um profissional mais próximo'],
        ],
        'educacao' => [
            'rotulo' => 'Educação e cursos',
            'estagios' => ['Interessado', 'Contato feito', 'Aula experimental', 'Matrícula em andamento'],
            'ganho' => 'Matriculado', 'perdido' => 'Perdido',
            'motivos' => ['Escolheu outra instituição', 'Horário incompatível'],
        ],
        'saude' => [
            'rotulo' => 'Saúde e estética',
            'estagios' => ['Contato', 'Avaliação agendada', 'Orçamento enviado'],
            'ganho' => 'Fechado', 'perdido' => 'Perdido',
            'motivos' => ['Procedimento adiado', 'Plano de saúde não cobre'],
        ],
        'imobiliario' => [
            'rotulo' => 'Imobiliário',
            'estagios' => ['Contato', 'Visita agendada', 'Proposta', 'Documentação'],
            'ganho' => 'Fechado', 'perdido' => 'Perdido',
            'motivos' => ['Financiamento negado', 'Imóvel não atendeu'],
        ],
        'industria' => [
            'rotulo' => 'Indústria e distribuição',
            'estagios' => ['Prospecção', 'Cotação enviada', 'Negociação', 'Pedido em análise'],
            'ganho' => 'Ganho', 'perdido' => 'Perdido',
            'motivos' => ['Prazo de entrega', 'Pedido mínimo acima do esperado'],
        ],
        'varejo' => [
            'rotulo' => 'Varejo e e-commerce',
            'estagios' => ['Contato', 'Atendimento', 'Orçamento'],
            'ganho' => 'Venda feita', 'perdido' => 'Perdido',
            'motivos' => ['Comprou em outra loja', 'Frete ou prazo de entrega'],
        ],
        'eventos' => [
            'rotulo' => 'Eventos e turismo',
            'estagios' => ['Contato', 'Orçamento enviado', 'Negociação'],
            'ganho' => 'Fechado', 'perdido' => 'Perdido',
            'motivos' => ['Data indisponível', 'Evento cancelado'],
        ],
        'outro' => [
            'rotulo' => 'Outro',
            'estagios' => null,
            'ganho' => null, 'perdido' => null,
            'motivos' => [],
        ],
    ],

    // `minimo` é o número de usuários sugerido na Assinatura.
    'tamanhos' => [
        '1' => ['rotulo' => '1 pessoa', 'minimo' => 1],
        '2' => ['rotulo' => '2 pessoas', 'minimo' => 2],
        '3' => ['rotulo' => '3 pessoas', 'minimo' => 3],
        '4' => ['rotulo' => '4 pessoas', 'minimo' => 4],
        '5' => ['rotulo' => '5 pessoas', 'minimo' => 5],
        '6-10' => ['rotulo' => '6 a 10 pessoas', 'minimo' => 6],
        '11-20' => ['rotulo' => '11 a 20 pessoas', 'minimo' => 11],
        '21-50' => ['rotulo' => '21 a 50 pessoas', 'minimo' => 21],
        '51+' => ['rotulo' => 'Mais de 50 pessoas', 'minimo' => 51],
    ],

    // `nome_do_lead` é o rótulo do nome no cadastro e na edição do lead.
    'para_quem' => [
        'empresas' => ['rotulo' => 'Empresas', 'nome_do_lead' => 'Nome da empresa'],
        'pessoas' => ['rotulo' => 'Pessoas', 'nome_do_lead' => 'Nome do cliente'],
        'ambos' => ['rotulo' => 'Empresas e pessoas', 'nome_do_lead' => 'Nome'],
    ],

    // `modelo` = [título do modelo de tarefa, prazo em dias]; null não cria modelo.
    'canais' => [
        'whatsapp' => ['rotulo' => 'WhatsApp', 'modelo' => ['Mandar WhatsApp de acompanhamento', 2]],
        'ligacao' => ['rotulo' => 'Ligação', 'modelo' => ['Ligar para retomar o contato', 3]],
        'email' => ['rotulo' => 'E-mail', 'modelo' => ['Enviar e-mail com a proposta', 1]],
        'reuniao_online' => ['rotulo' => 'Reunião online', 'modelo' => ['Confirmar a reunião', 1]],
        'reuniao_presencial' => ['rotulo' => 'Reunião presencial', 'modelo' => ['Confirmar a reunião', 1]],
        'visita' => ['rotulo' => 'Visita', 'modelo' => ['Agendar a visita', 2]],
        'linkedin' => ['rotulo' => 'LinkedIn', 'modelo' => ['Conectar no LinkedIn', 0]],
        'redes' => ['rotulo' => 'Instagram ou Facebook', 'modelo' => ['Responder pelo direct', 0]],
        'outro' => ['rotulo' => 'Outro', 'modelo' => null],
    ],

    // Os rótulos escolhidos viram as opções do campo Origem do lead (mais "Outro").
    'origens' => [
        'indicacao' => 'Indicação',
        'site' => 'Site',
        'redes_sociais' => 'Redes sociais',
        'anuncios' => 'Anúncios (tráfego pago)',
        'prospeccao' => 'Prospecção ativa',
        'eventos' => 'Eventos e feiras',
        'marketplace' => 'Marketplace',
        'outro' => 'Outro',
    ],

    'conheceu' => [
        'google' => 'Google',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'indicacao' => 'Indicação',
        'anuncio' => 'Anúncio',
        'evento' => 'Evento',
        'outro' => 'Outro',
    ],
];
