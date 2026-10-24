import { formatBRL, formatarData } from "./formatadores";

const emDias = (n) => (n <= 0 ? "hoje" : n === 1 ? "amanhã" : `em ${n} dias`);

/**
 * Faixa do topo do app a partir do resumo da assinatura (props.assinatura).
 * Só aparece quando há algo a fazer: teste ou plano perto do fim, fatura
 * vencendo, carência e só leitura (com a data da suspensão).
 *
 * @returns {{ tom: 'warn'|'danger', texto: string, acao: string } | null}
 */
export function faixaDaAssinatura(a) {
    if (!a) return null;
    const fatura = a.fatura_pendente;
    const suspensao = a.suspende_em
        ? ` O acesso será suspenso em ${formatarData(a.suspende_em)}.`
        : "";

    switch (a.status) {
        case "teste_expirado":
            return {
                tom: "danger",
                texto: `Seu período de teste terminou: a equipe só consulta.${suspensao}`,
                acao: fatura ? "Pagar fatura" : "Escolher plano",
            };
        case "vencida":
            return {
                tom: "danger",
                texto: `O plano ${a.plano_nome} venceu e a equipe só consulta.${suspensao}`,
                acao: fatura ? "Pagar fatura" : "Renovar",
            };
        case "cancelada":
            return {
                tom: "danger",
                texto: `A assinatura foi cancelada e a equipe só consulta.${suspensao}`,
                acao: fatura ? "Pagar fatura" : "Escolher plano",
            };
        case "carencia":
            return {
                tom: "danger",
                texto: `O plano ${a.plano_nome} venceu. A equipe trabalha normalmente até ${formatarData(a.carencia_ate)}; depois disso, só consulta.`,
                acao: "Pagar fatura",
            };
        case "teste":
            if (a.dias_restantes > 7) return null;
            return {
                tom: "warn",
                texto: `Seu teste termina ${emDias(a.dias_restantes)}.${fatura ? ` Fatura de ${formatBRL(fatura.valor)} aguardando pagamento.` : ""}`,
                acao: fatura ? "Ver fatura" : "Ver planos",
            };
        case "ativa":
            if (a.renovacao_cancelada) {
                return a.dias_restantes <= 7
                    ? {
                          tom: "warn",
                          texto: `Seu plano termina ${emDias(a.dias_restantes)} e não será renovado.`,
                          acao: "Retomar renovação",
                      }
                    : null;
            }
            if (fatura && a.dias_restantes <= 7)
                return {
                    tom: "warn",
                    texto: `A fatura de ${formatBRL(fatura.valor)} vence ${emDias(a.dias_restantes)}.`,
                    acao: "Ver fatura",
                };
            if (a.dias_restantes <= 5)
                return {
                    tom: "warn",
                    texto: `Seu plano vence ${emDias(a.dias_restantes)}.`,
                    acao: "Renovar",
                };
            return null;
        default:
            return null;
    }
}
