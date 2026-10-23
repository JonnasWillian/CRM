@extends('site.layout')

@section('conteudo')
    <article class="mx-auto max-w-3xl px-4 py-14 md:px-6">
        <h1 class="font-display text-3xl font-bold tracking-tight">Política de privacidade</h1>
        <p class="mt-2 text-sm text-fg-2">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-10-22')->translatedFormat('d \d\e F \d\e Y') }} · Conforme a Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018)</p>

        @foreach ([
            ['Quem trata os dados', "{$empresa['razao_social']}".($empresa['cnpj'] ? ", CNPJ {$empresa['cnpj']}" : '').", é a controladora dos dados de cadastro dos usuários do {$nome} e operadora dos dados que cada empresa cliente registra no sistema (leads, contatos, contratos). O encarregado de proteção de dados (DPO) pode ser contatado em {$empresa['dpo_email']}."],
            ['Dados que coletamos', 'Dados de cadastro: nome, e-mail, senha (armazenada com hash), nome da empresa e papel na equipe. Dados de uso: páginas acessadas, endereço IP, navegador e registros de atividade dentro da conta (quem alterou o quê), necessários para segurança e auditoria. Dados registrados por você: tudo o que sua empresa cadastra sobre leads, clientes e negócios.'],
            ['Para que usamos', 'Prestar o serviço contratado (base legal: execução de contrato); manter a segurança, prevenir fraude e cumprir obrigações legais (base legal: obrigação legal e legítimo interesse); enviar comunicações sobre a conta, como convites, redefinição de senha e avisos de cobrança (execução de contrato); e, apenas com o seu consentimento, novidades do produto.'],
            ['Dados de terceiros registrados por você', 'Os dados de leads e clientes que sua empresa registra são tratados por nós apenas sob suas instruções, para operar o sistema. Sua empresa é a controladora desses dados e responde pela base legal para coletá-los. Fornecemos as ferramentas para atender os direitos dos titulares: edição, exportação em CSV e exclusão.'],
            ['Compartilhamento', 'Não vendemos dados. Compartilhamos apenas com fornecedores necessários à operação, como hospedagem, envio de e-mail e processamento de pagamento, todos sob contrato e com o mínimo necessário. Podemos divulgar dados por ordem judicial ou obrigação legal.'],
            ['Armazenamento e segurança', 'Os dados de cada empresa ficam isolados por identificador de empresa em todas as consultas. Usamos conexão criptografada (HTTPS), senhas com hash, controle de acesso por papel, registro de atividades e cópias de segurança periódicas. Nenhum sistema é infalível; em caso de incidente com risco aos titulares, comunicaremos a ANPD e os afetados nos prazos legais.'],
            ['Retenção', 'Mantemos os dados enquanto a conta estiver ativa. Após o cancelamento, os dados da empresa são apagados em até 30 dias, exceto o que precisarmos guardar por obrigação legal (por exemplo, registros fiscais de pagamento).'],
            ['Seus direitos', 'Você pode confirmar a existência de tratamento, acessar, corrigir, anonimizar, portar ou excluir seus dados, e revogar consentimentos. Dentro do sistema: edite seu perfil, exporte os leads em CSV ou exclua a conta. Para o restante, escreva para o DPO; respondemos em até 15 dias.'],
            ['Cookies', 'Usamos apenas cookies necessários ao funcionamento (sessão e proteção contra falsificação de requisição) e, se configurado, uma ferramenta de métricas de audiência com IP anonimizado. Preferências de interface, como o tema, ficam no seu navegador e não saem dele.'],
            ['Alterações', 'Esta política pode ser atualizada. Mudanças relevantes serão avisadas por e-mail e nesta página, com a data da última atualização no topo.'],
        ] as [$t, $p])
            <h2 class="mt-8 font-display text-xl font-semibold">{{ $t }}</h2>
            <p class="mt-2 text-md text-fg-1">{{ $p }}</p>
        @endforeach
    </article>
@endsection
