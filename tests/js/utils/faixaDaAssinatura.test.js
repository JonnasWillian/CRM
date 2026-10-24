import { describe, expect, it } from "vitest";
import { faixaDaAssinatura } from "@/utils/faixaDaAssinatura";
import { formatBRL } from "@/utils/formatadores";

const base = {
    plano_nome: "Profissional",
    dias_restantes: 20,
    fatura_pendente: null,
    renovacao_cancelada: false,
    suspende_em: null,
    carencia_ate: null,
};
const fatura = { valor: 267, vence_em: "2026-10-31" };

describe("faixaDaAssinatura", () => {
    it("não mostra nada sem assinatura ou com plano ativo longe do vencimento", () => {
        expect(faixaDaAssinatura(null)).toBeNull();
        expect(faixaDaAssinatura({ ...base, status: "ativa" })).toBeNull();
        expect(
            faixaDaAssinatura({ ...base, status: "teste", dias_restantes: 8 }),
        ).toBeNull();
    });

    it("teste acabando avisa os dias e a fatura aguardando pagamento", () => {
        expect(
            faixaDaAssinatura({ ...base, status: "teste", dias_restantes: 3 }),
        ).toEqual({
            tom: "warn",
            texto: "Seu teste termina em 3 dias.",
            acao: "Ver planos",
        });
        expect(
            faixaDaAssinatura({
                ...base,
                status: "teste",
                dias_restantes: 0,
                fatura_pendente: fatura,
            }),
        ).toEqual({
            tom: "warn",
            texto: `Seu teste termina hoje. Fatura de ${formatBRL(267)} aguardando pagamento.`,
            acao: "Ver fatura",
        });
    });

    it("plano ativo: fatura vencendo, vencimento sem fatura e renovação cancelada", () => {
        expect(
            faixaDaAssinatura({
                ...base,
                status: "ativa",
                dias_restantes: 1,
                fatura_pendente: fatura,
            }),
        ).toEqual({
            tom: "warn",
            texto: `A fatura de ${formatBRL(267)} vence amanhã.`,
            acao: "Ver fatura",
        });
        expect(
            faixaDaAssinatura({ ...base, status: "ativa", dias_restantes: 5 }),
        ).toEqual({
            tom: "warn",
            texto: "Seu plano vence em 5 dias.",
            acao: "Renovar",
        });
        expect(
            faixaDaAssinatura({
                ...base,
                status: "ativa",
                dias_restantes: 6,
                renovacao_cancelada: true,
            }),
        ).toEqual({
            tom: "warn",
            texto: "Seu plano termina em 6 dias e não será renovado.",
            acao: "Retomar renovação",
        });
    });

    it("carência diz até quando a equipe trabalha", () => {
        expect(
            faixaDaAssinatura({
                ...base,
                status: "carencia",
                carencia_ate: "2026-10-26",
                fatura_pendente: fatura,
            }),
        ).toEqual({
            tom: "danger",
            texto: "O plano Profissional venceu. A equipe trabalha normalmente até 26/10/2026; depois disso, só consulta.",
            acao: "Pagar fatura",
        });
    });

    it("só leitura diz o motivo e a data da suspensão", () => {
        const suspende = { suspende_em: "2026-11-22" };
        expect(
            faixaDaAssinatura({
                ...base,
                ...suspende,
                status: "teste_expirado",
            }),
        ).toEqual({
            tom: "danger",
            texto: "Seu período de teste terminou: a equipe só consulta. O acesso será suspenso em 22/11/2026.",
            acao: "Escolher plano",
        });
        expect(
            faixaDaAssinatura({
                ...base,
                ...suspende,
                status: "vencida",
                fatura_pendente: fatura,
            }).texto,
        ).toBe(
            "O plano Profissional venceu e a equipe só consulta. O acesso será suspenso em 22/11/2026.",
        );
        expect(
            faixaDaAssinatura({
                ...base,
                ...suspende,
                status: "vencida",
                fatura_pendente: fatura,
            }).acao,
        ).toBe("Pagar fatura");
        expect(
            faixaDaAssinatura({ ...base, ...suspende, status: "cancelada" })
                .texto,
        ).toBe(
            "A assinatura foi cancelada e a equipe só consulta. O acesso será suspenso em 22/11/2026.",
        );
    });
});
