@extends('site.layout')

@section('conteudo')
    <article class="prose-site mx-auto max-w-3xl px-4 py-14 md:px-6">
        <h1 class="font-display text-3xl font-bold tracking-tight">Termos de uso</h1>
        <p class="mt-2 text-sm text-fg-2">Última atualização: {{ \Illuminate\Support\Carbon::parse('2026-10-22')->translatedFormat('d \d\e F \d\e Y') }}</p>

        @foreach ([
            ['1. Quem somos', "O {$nome} é um serviço de CRM (gestão de relacionamento com clientes) oferecido por {$empresa['razao_social']}".($empresa['cnpj'] ? ", CNPJ {$empresa['cnpj']}" : '').", com sede em {$empresa['cidade']} (\"nós\"). Ao criar uma conta, você (\"cliente\") aceita estes termos em nome da empresa que cadastrou."],
            ['2. O serviço', 'Fornecemos acesso, pela internet, a um sistema para registrar leads, oportunidades, tarefas, contratos e a equipe de vendas da sua empresa. O serviço é oferecido como está, com evolução contínua; podemos acrescentar, alterar ou retirar recursos, avisando com antecedência razoável quando a mudança afetar o uso.'],
            ['3. Conta e equipe', 'A pessoa que cria a conta é o administrador da empresa e responde pelos usuários que convidar. Cada usuário deve ter credenciais próprias e intransferíveis. Você é responsável por manter a senha em sigilo e por tudo o que for feito com o seu acesso.'],
            ['4. Teste grátis e planos', "Toda conta começa com ".config('comercial.dias_de_teste')." dias de uso gratuito, sem cartão. Depois disso, a continuidade depende da contratação de um plano, cobrado por usuário ativo e por mês ou por ano, em reais, conforme a tabela publicada em /precos. Preços podem ser reajustados com aviso de 30 dias; o reajuste vale a partir da renovação seguinte."],
            ['5. Cancelamento', 'Você pode cancelar a qualquer momento. No plano mensal, o acesso continua até o fim do período pago; no anual, até o fim do ano contratado, sem reembolso proporcional, salvo quando a lei determinar. Antes de cancelar, exporte seus dados: após 30 dias do encerramento, os dados são apagados definitivamente.'],
            ['6. Seus dados', 'Os dados que você registra (leads, contatos, contratos, anotações) são seus. Nós os tratamos apenas para prestar o serviço, nos termos da Política de privacidade. Você pode exportá-los em CSV a qualquer momento. Você garante ter base legal, conforme a LGPD, para registrar os dados de terceiros no sistema.'],
            ['7. Uso aceitável', 'É proibido usar o serviço para fins ilegais, enviar spam, tentar acessar dados de outras empresas, sobrecarregar a plataforma ou fazer engenharia reversa. Contas que violarem estas regras podem ser suspensas após aviso, ou imediatamente em caso de risco à segurança.'],
            ['8. Disponibilidade e suporte', 'Trabalhamos para manter o serviço disponível de forma contínua, com manutenções programadas avisadas com antecedência. O suporte é feito por e-mail em horário comercial. Nos planos com suporte prioritário, o prazo de primeira resposta é informado na contratação.'],
            ['9. Responsabilidade', 'Na extensão permitida pela lei, nossa responsabilidade por danos decorrentes do uso do serviço fica limitada ao valor pago pelo cliente nos 12 meses anteriores ao fato. Não respondemos por lucros cessantes nem por decisões comerciais tomadas com base nos relatórios do sistema.'],
            ['10. Alterações e foro', 'Podemos atualizar estes termos; mudanças relevantes serão comunicadas por e-mail com 15 dias de antecedência. Fica eleito o foro da comarca da nossa sede para resolver qualquer disputa, com renúncia a qualquer outro.'],
        ] as [$t, $p])
            <h2 class="mt-8 font-display text-xl font-semibold">{{ $t }}</h2>
            <p class="mt-2 text-md text-fg-1">{{ $p }}</p>
        @endforeach

        <p class="mt-10 text-md text-fg-1">Dúvidas sobre estes termos: <a href="mailto:{{ $contato['email'] }}" class="font-medium text-primary hover:underline">{{ $contato['email'] }}</a>.</p>
    </article>
@endsection
