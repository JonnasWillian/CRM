import { flushPromises, mount } from "@vue/test-utils";
import { reactive } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { formatBRL } from "@/utils/formatadores";

const post = vi.fn();
const confirmar = vi.fn().mockResolvedValue(true);

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { auth: { permissions: {} } } }),
    useForm: (dados) =>
        reactive({ ...dados, errors: {}, processing: false, post: vi.fn() }),
    router: { post: (...args) => post(...args) },
    Head: { template: "<div />" },
    Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
}));
vi.mock("@/Composables/useConfirm", () => ({
    useConfirm: () => ({ confirmar }),
}));

globalThis.route = (nome) => `/${nome}`;

const { default: Assinatura } =
    await import("@/Pages/Configuracoes/Assinatura.vue");

const PLANOS = [
    {
        chave: "inicial",
        nome: "Inicial",
        preco_mensal: 49,
        preco_anual: 39,
        descricao: "d",
        limite: "Até 3 usuários",
        inclui: [],
        destaque: false,
        excessos: [
            "O plano Inicial permite até 3 usuários ativos e a empresa tem 4. Desative 1 em Configurações › Equipe ou escolha outro plano.",
        ],
        valor: { mensal: 196, anual: 1872 },
        usuarios: 4,
    },
    {
        chave: "profissional",
        nome: "Profissional",
        preco_mensal: 89,
        preco_anual: 69,
        descricao: "d",
        limite: "Ilimitado",
        inclui: [],
        destaque: true,
        excessos: [],
        valor: { mensal: 356, anual: 3312 },
        usuarios: 4,
    },
    { chave: 'empresa', nome: 'Empresa', preco_mensal: 139, preco_anual: 109, descricao: 'd', limite: 'A partir de 5 usuários', inclui: [], destaque: false, excessos: [], valor: { mensal: 695, anual: 6540 }, usuarios: 5 },
];
const assinatura = (extra = {}) => ({
    status: "teste",
    plano: "profissional",
    plano_nome: "Profissional",
    ciclo: null,
    dias_restantes: 10,
    teste_termina_em: "2026-11-03",
    vigente_ate: null,
    bloqueada: false,
    carencia_ate: null,
    somente_leitura_desde: null,
    suspende_em: null,
    suspensa: false,
    renovacao_cancelada: false,
    limites: { usuarios: null, funis: null, leads: null },
    uso: { usuarios: 4, funis: 1, leads: 10 },
    convites_pendentes: 0,
    fatura_pendente: null,
    ...extra,
});

function montar(props = {}) {
    return mount(Assinatura, {
        props: {
            assinatura: assinatura(),
            planos: PLANOS,
            usuariosCobrados: 4,
            faturaPendente: null,
            faturas: [],
            pagamento: {
                instrucoes: null,
                email: "comercial@crm.test",
                whatsapp: null,
            },
            diasDeTeste: 14,
            ...props,
        },
        global: {
            mocks: { route: globalThis.route },
            stubs: { AuthenticatedLayout: { template: "<div><slot /></div>" } },
        },
    });
}

describe("Configurações › Assinatura", () => {
    beforeEach(() => {
        post.mockClear();
        confirmar.mockClear();
    });

    it("plano em que o uso não cabe explica o motivo e não deixa contratar", () => {
        const w = montar();
        const cartao = w.find('[data-plano="inicial"]');
        expect(cartao.text()).toContain("a empresa tem 4");
        const botao = cartao.find("button");
        expect(botao.text()).toBe("Não cabe no uso atual");
        expect(botao.attributes("disabled")).toBeDefined();
    });

    it("mostra o total para os usuários de hoje no ciclo escolhido", async () => {
        const w = montar();
        expect(w.find('[data-plano="profissional"]').text()).toContain(
            `4 usuários: ${formatBRL(3312)} por ano`,
        );
        await w.find('[role="radio"]').trigger("click"); // mensal
        expect(w.find('[data-plano="profissional"]').text()).toContain(
            `4 usuários: ${formatBRL(356)} por mês`,
        );
    });

    it('plano com mínimo de usuários mostra a quantidade cobrada nele', () => {
        const w = montar();
        expect(w.find('[data-plano="empresa"]').text()).toContain(`5 usuários: ${formatBRL(6540)} por ano`);
    });

    it("contratar confirma o valor e gera a fatura, sem prometer ativação imediata", async () => {
        const w = montar();
        await w.find('[data-plano="profissional"] button').trigger("click");
        await flushPromises();
        const pedido = confirmar.mock.calls[0][0];
        expect(pedido.titulo).toBe("Gerar fatura do plano Profissional?");
        expect(pedido.mensagem).toContain(formatBRL(3312));
        expect(pedido.mensagem).toContain(
            "ativado assim que o pagamento for confirmado",
        );
        expect(pedido.rotuloConfirmar).toBe("Gerar fatura");
    });

    it("fatura pendente mostra valor, vencimento, como pagar e o contato", () => {
        const w = montar({
            faturaPendente: {
                id: 7,
                plano_nome: "Profissional",
                ciclo: "anual",
                usuarios: 4,
                valor: 3312,
                vence_em: "2026-11-03",
                atrasada: false,
                status: "pendente",
            },
            pagamento: {
                instrucoes: "Pix: financeiro@crm.test",
                email: "comercial@crm.test",
                whatsapp: "5511999998888",
            },
        });
        const bloco = w.find("[data-fatura-pendente]");
        expect(bloco.text()).toContain(formatBRL(3312));
        expect(bloco.text()).toContain("03/11/2026");
        expect(bloco.text()).toContain("Pix: financeiro@crm.test");
        expect(bloco.find('a[href="mailto:comercial@crm.test"]').exists()).toBe(
            true,
        );
        expect(
            bloco.find('a[href="https://wa.me/5511999998888"]').exists(),
        ).toBe(true);
    });

    it("plano ativo oferece cancelar a renovação; cancelada, oferece retomar", async () => {
        const ativa = montar({
            assinatura: assinatura({
                status: "ativa",
                ciclo: "mensal",
                vigente_ate: "2026-11-24",
                dias_restantes: 31,
            }),
        });
        const cancelar = ativa
            .findAll("button")
            .find((b) => b.text() === "Cancelar renovação");
        expect(cancelar).toBeDefined();
        await cancelar.trigger("click");
        await flushPromises();
        expect(confirmar.mock.calls[0][0].tom).toBe("danger");
        expect(post).toHaveBeenCalledWith(
            "/configuracoes.assinatura.cancelar-renovacao",
            {},
            expect.any(Object),
        );

        const cancelada = montar({
            assinatura: assinatura({
                status: "ativa",
                ciclo: "mensal",
                vigente_ate: "2026-11-24",
                renovacao_cancelada: true,
            }),
        });
        expect(
            cancelada
                .findAll("button")
                .some((b) => b.text() === "Retomar renovação"),
        ).toBe(true);
        expect(cancelada.text()).toContain("não será renovado");
    });

    it("lista o histórico de faturas com a situação de cada uma", () => {
        const w = montar({
            faturas: [
                {
                    id: 8,
                    plano_nome: "Profissional",
                    ciclo: "mensal",
                    usuarios: 4,
                    valor: 356,
                    vence_em: "2026-10-20",
                    paga_em: null,
                    status: "pendente",
                    atrasada: true,
                },
                {
                    id: 6,
                    plano_nome: "Inicial",
                    ciclo: "mensal",
                    usuarios: 3,
                    valor: 147,
                    vence_em: "2026-09-20",
                    paga_em: "2026-09-19",
                    status: "paga",
                    atrasada: false,
                },
                {
                    id: 5,
                    plano_nome: "Inicial",
                    ciclo: "mensal",
                    usuarios: 3,
                    valor: 147,
                    vence_em: "2026-09-18",
                    paga_em: null,
                    status: "cancelada",
                    atrasada: false,
                },
            ],
        });
        const linhas = w.findAll("[data-faturas] tbody tr");
        expect(linhas).toHaveLength(3);
        expect(linhas[0].text()).toContain("Atrasada");
        expect(linhas[1].text()).toContain("Paga em 19/09/2026");
        expect(linhas[2].text()).toContain("Cancelada");
    });
});
