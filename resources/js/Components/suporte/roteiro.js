import { Bug, CircleHelp, CreditCard, Heart, Lightbulb } from 'lucide-vue-next';

/**
 * Roteiro do assistente de ajuda: assuntos, áreas, impacto e as dicas
 * mostradas antes de abrir um chamado de dúvida. As chaves são as mesmas de
 * config/suporte.php (o servidor recusa o que não estiver lá).
 */
export const ASSUNTOS = [
    { valor: 'problema', rotulo: 'Algo não funciona', detalhe: 'Erro, travamento ou resultado errado', icone: Bug },
    { valor: 'duvida', rotulo: 'Tenho uma dúvida', detalhe: 'Como fazer algo no sistema', icone: CircleHelp },
    { valor: 'sugestao', rotulo: 'Tenho uma sugestão', detalhe: 'Uma ideia para melhorar', icone: Lightbulb },
    { valor: 'cobranca', rotulo: 'Cobrança e assinatura', detalhe: 'Plano, fatura ou pagamento', icone: CreditCard },
    { valor: 'elogio', rotulo: 'Quero elogiar', detalhe: 'Algo que funcionou bem para você', icone: Heart },
];

export const AREAS = [
    { valor: 'dashboard', rotulo: 'Dashboard' },
    { valor: 'leads', rotulo: 'Leads' },
    { valor: 'pipeline', rotulo: 'Pipeline' },
    { valor: 'tarefas', rotulo: 'Tarefas' },
    { valor: 'relatorios', rotulo: 'Relatórios' },
    { valor: 'importacao', rotulo: 'Importação de planilha' },
    { valor: 'equipe', rotulo: 'Equipe e convites' },
    { valor: 'assinatura', rotulo: 'Assinatura e pagamento' },
    { valor: 'acesso', rotulo: 'Login e conta' },
    { valor: 'outro', rotulo: 'Outro' },
];

export const IMPACTOS = [
    { valor: 'bloqueia', rotulo: 'Não consigo trabalhar' },
    { valor: 'atrapalha', rotulo: 'Atrapalha, mas consigo trabalhar' },
    { valor: 'detalhe', rotulo: 'É um detalhe' },
];

// A tela em que a pessoa está já responde "em que parte do sistema?".
const ROTAS = [
    ['leads.importar*', 'importacao'],
    ['leads.*', 'leads'],
    ['kanban', 'pipeline'],
    ['tarefas.*', 'tarefas'],
    ['relatorios.*', 'relatorios'],
    ['configuracoes.equipe', 'equipe'],
    ['configuracoes.assinatura*', 'assinatura'],
    ['profile.*', 'acesso'],
    ['dashboard', 'dashboard'],
];

export function areaDaTela() {
    if (typeof route !== 'function') return 'outro';
    const atual = route();
    return ROTAS.find(([padrao]) => atual.current(padrao))?.[1] ?? 'outro';
}

export const PERGUNTA_DO_RELATO = {
    problema: { titulo: 'O que aconteceu?', exemplo: 'O que você tentou fazer, o que esperava e o que apareceu na tela.' },
    duvida: { titulo: 'Qual é a sua dúvida?', exemplo: 'O que você quer fazer e onde travou.' },
    sugestao: { titulo: 'Qual é a sua ideia?', exemplo: 'O que você gostaria de fazer e hoje não consegue.' },
    cobranca: { titulo: 'O que aconteceu com a cobrança?', exemplo: 'Qual fatura, forma de pagamento e o que apareceu.' },
    elogio: { titulo: 'O que funcionou bem?', exemplo: 'Conte o que ajudou no seu dia a dia.' },
};

export const DICAS = {
    dashboard: [
        { titulo: 'Mude o período e o vendedor', texto: 'No topo do Dashboard, escolha 7 dias, 30 dias, mês atual ou um período personalizado. Quem vê a equipe toda também filtra por vendedor.' },
        { titulo: 'Os números levam aos leads', texto: 'Clique em um indicador, como "Leads ativos", para abrir a lista de leads já filtrada.' },
    ],
    leads: [
        { titulo: 'Cadastrar um lead', texto: 'Em Leads ou no Pipeline, use o botão "Novo lead". Nome e e-mail são obrigatórios.' },
        { titulo: 'Escolher as colunas da lista', texto: 'No canto direito da tabela, o ícone de colunas mostra ou esconde colunas; o ícone ao lado alterna a altura das linhas.' },
        { titulo: 'Lead apagado sem querer', texto: 'Ele vai para a Lixeira, no menu lateral, e pode ser restaurado de lá.' },
    ],
    pipeline: [
        { titulo: 'Mover um lead de estágio', texto: 'Arraste o cartão para outra coluna, ou abra o menu do cartão e escolha o estágio.' },
        { titulo: 'Mudar os estágios do funil', texto: 'Em Configurações › Funis, o administrador renomeia, reordena e cria estágios.' },
    ],
    tarefas: [
        { titulo: 'Criar uma tarefa', texto: 'Abra o lead e use "Nova tarefa". Ela aparece em Tarefas, separada entre atrasadas, de hoje e próximas.' },
        { titulo: 'Tarefas que se repetem', texto: 'Em Configurações › Modelos de tarefa, crie modelos e aplique vários de uma vez no lead.' },
    ],
    relatorios: [
        { titulo: 'Por que perdemos', texto: 'O relatório de perdas agrupa os leads perdidos pelo motivo informado na hora da perda.' },
        { titulo: 'Quem vê os relatórios', texto: 'Relatórios aparecem para gestores e administradores, que veem os leads de toda a equipe.' },
    ],
    importacao: [
        { titulo: 'Importar uma planilha', texto: 'Em Leads › Importar, envie um arquivo CSV, confira o mapeamento das colunas e confirme. Há um modelo de CSV para baixar na própria tela.' },
        { titulo: 'Linhas com erro', texto: 'Ao final, a tela lista as linhas que não entraram e o motivo. Corrija só essas e importe de novo.' },
    ],
    equipe: [
        { titulo: 'Convidar alguém', texto: 'Em Configurações › Equipe, use "Convidar membro". A pessoa recebe um e-mail com o link, válido por 7 dias.' },
        { titulo: 'Papéis', texto: 'Vendedor vê só os próprios leads. Gestor e administrador veem os de toda a equipe.' },
    ],
    assinatura: [
        { titulo: 'Pagar a fatura', texto: 'Em Configurações › Assinatura, use "Pagar com Pix" ou "Boleto ou cartão". A confirmação é automática.' },
        { titulo: 'Paguei e não confirmou', texto: 'Na mesma tela, use "Conferir pagamento". O Pix precisa ser feito pelo QR Code ou pelo código da própria fatura.' },
    ],
    acesso: [
        { titulo: 'Esqueci a senha', texto: 'Na tela de entrada, use "Esqueci a senha" para receber o link de redefinição por e-mail.' },
        { titulo: 'Trocar nome, e-mail ou senha', texto: 'No menu da sua conta, no rodapé do menu lateral, abra "Perfil e conta".' },
    ],
    outro: [
        { titulo: 'Rever o passo a passo', texto: 'No menu da sua conta, no rodapé do menu lateral, use "Refazer o passo a passo".' },
    ],
};
