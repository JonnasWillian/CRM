import { ehErroDeValidacao, mensagemDeErro } from './erros';

/**
 * Tratamento único de erro HTTP do axios. Nenhum `catch {}` vazio sobrevive:
 * quem faz `catch` só decide rollback/estado local; a mensagem ao usuário
 * sai daqui. `config.silencioso = true` desliga para uma chamada.
 *
 * 422 com campos fica em silêncio de propósito — o formulário mostra o erro
 * no campo. 422 sem campos é regra de domínio com frase pronta do backend.
 */
export const MENSAGENS = {
    402: 'Sua assinatura não permite alterações no momento. Escolha um plano em Configurações › Assinatura.',
    403: 'Você não tem permissão para esta ação.',
    404: 'Registro não encontrado. Pode ter sido removido por outra pessoa.',
    419: 'Sua sessão expirou.',
    429: 'Muitas tentativas. Aguarde um instante.',
    generica: 'Não foi possível concluir. Tente novamente.',
};

export function tratarErroHttp(erro, { toast, aoDeslogar } = {}) {
    // Assinatura bloqueada (402) é global, como 401: vale mesmo quando o chamador pediu silêncio.
    if (erro?.config?.silencioso && erro?.response?.status !== 402) return false;
    if (typeof axios !== 'undefined' && axios.isCancel?.(erro)) return false;
    if (erro?.code === 'ERR_CANCELED' || erro?.name === 'CanceledError') return false;

    const status = erro?.response?.status;

    switch (status) {
        case 401:
            aoDeslogar?.();
            return true;
        case 402:
            toast?.erro(erro?.response?.data?.message || MENSAGENS[402], { acao: { rotulo: 'Ver planos', onClick: () => { window.location.href = '/configuracoes/assinatura'; } } });
            return true;
        case 403:
        case 404:
        case 429:
            toast?.erro(MENSAGENS[status]);
            return true;
        case 419:
            toast?.erro(MENSAGENS[419], { acao: { rotulo: 'Recarregar', onClick: () => window.location.reload() } });
            return true;
        case 422:
            if (ehErroDeValidacao(erro)) return false;
            toast?.erro(mensagemDeErro(erro, MENSAGENS.generica));
            return true;
        default:
            toast?.erro(mensagemDeErro(erro, MENSAGENS.generica));
            return true;
    }
}

export function instalarInterceptor(instancia, dependencias) {
    instancia.interceptors.response.use(
        (resposta) => resposta,
        (erro) => {
            tratarErroHttp(erro, dependencias);
            return Promise.reject(erro);
        },
    );
}
