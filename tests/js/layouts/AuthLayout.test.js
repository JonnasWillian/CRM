import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { app: { nome: "MeuCRM" } } }),
    Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
}));

const { default: AuthLayout } = await import("@/Layouts/AuthLayout.vue");
const { default: PainelDeEntrada } =
    await import("@/Components/auth/PainelDeEntrada.vue");

describe("AuthLayout", () => {
    it("sem painel: cartão centrado com marca, título e rodapé (recuperar senha, convite…)", () => {
        const w = mount(AuthLayout, {
            props: {
                titulo: "Esqueci a senha",
                descricao: "Enviamos um link.",
            },
            slots: { default: '<form id="f" />', rodape: "Voltar" },
        });
        expect(w.find("h1").text()).toBe("Esqueci a senha");
        expect(w.text()).toContain("MeuCRM");
        expect(w.find("#f").exists()).toBe(true);
        expect(w.find("[data-painel]").exists()).toBe(false);
    });

    it("com painel: duas colunas, o painel só a partir de lg e uma faixa da marca no celular", () => {
        const w = mount(AuthLayout, {
            props: { titulo: "Entrar" },
            slots: {
                default: '<form id="f" />',
                painel: "<p data-conteudo-painel>Painel</p>",
            },
        });
        const painel = w.find("[data-painel]");
        expect(painel.exists()).toBe(true);
        expect(painel.classes()).toContain("hidden");
        expect(painel.classes()).toContain("lg:flex");
        expect(w.find("[data-conteudo-painel]").exists()).toBe(true);
        expect(w.find("[data-faixa-mobile]").classes()).toContain("lg:hidden");
    });
});

describe("PainelDeEntrada", () => {
    it("mostra marca, título, a miniatura de receita e os passos numerados quando são uma sequência", () => {
        const w = mount(PainelDeEntrada, {
            props: {
                titulo: "Em cinco minutos",
                descricao: "d",
                passos: [
                    "Crie a conta",
                    "Importe a planilha",
                    "Convide a equipe",
                ],
            },
        });
        expect(w.find("h2").text()).toBe("Em cinco minutos");
        expect(w.findAll("ol li")).toHaveLength(3);
        expect(w.find("[data-miniatura]").exists()).toBe(true);
        expect(w.text()).toContain("Faturando");
        expect(w.text()).toContain("exemplo");
    });

    it("sem passos não desenha a lista", () => {
        const w = mount(PainelDeEntrada, {
            props: { titulo: "Sua carteira", descricao: "d" },
        });
        expect(w.find("ol").exists()).toBe(false);
    });
});
