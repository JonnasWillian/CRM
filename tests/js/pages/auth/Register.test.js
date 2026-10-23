import { mount } from "@vue/test-utils";
import { reactive, ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";

const paginaProps = ref({
    app: {
        nome: "MeuCRM",
        dias_de_teste: 14,
        planos: [
            {
                chave: "inicial",
                nome: "Inicial",
                preco_mensal: 49,
                preco_anual: 39,
            },
            {
                chave: "profissional",
                nome: "Profissional",
                preco_mensal: 89,
                preco_anual: 69,
            },
        ],
    },
});

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({
        get props() {
            return paginaProps.value;
        },
    }),
    useForm: (dados) =>
        reactive({
            ...dados,
            errors: {},
            processing: false,
            post: vi.fn(),
            reset: vi.fn(),
        }),
    Head: { template: "<div />" },
    Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
}));

globalThis.route = (nome) => `/${nome}`;

const { default: Register } = await import("@/Pages/Auth/Register.vue");
const { default: CampoDeSenha } =
    await import("@/Components/auth/CampoDeSenha.vue");

const montar = () =>
    mount(Register, { global: { mocks: { route: globalThis.route } } });

describe("Register", () => {
    beforeEach(() => {
        window.history.replaceState({}, "", "/register");
    });

    it("com ?plano= conhecido, diz qual plano começa em teste e deixa trocar", () => {
        window.history.replaceState({}, "", "/register?plano=profissional");
        const w = montar();
        const chip = w.find("[data-plano-escolhido]");
        expect(chip.exists()).toBe(true);
        expect(chip.text()).toContain("Profissional");
        expect(chip.text()).toContain("14 dias");
        expect(chip.find('a[href="/precos"]').exists()).toBe(true);
    });

    it("sem plano (ou plano desconhecido) não inventa chip, mas ainda promete o teste grátis", () => {
        window.history.replaceState({}, "", "/register?plano=ouro");
        const w = montar();
        expect(w.find("[data-plano-escolhido]").exists()).toBe(false);
        expect(w.text()).toContain("14 dias");
    });

    it("pede uma senha só (com mostrar/ocultar) e aponta termos e privacidade", () => {
        const w = montar();
        expect(w.findAll('input[type="password"]')).toHaveLength(1);
        expect(w.find('a[href="/termos"]').exists()).toBe(true);
        expect(w.find('a[href="/privacidade"]').exists()).toBe(true);
    });
});

describe("CampoDeSenha", () => {
    it("alterna entre ocultar e mostrar a senha sem perder o valor", async () => {
        const w = mount(CampoDeSenha, {
            props: { modelValue: "segredo", autocomplete: "new-password" },
        });
        expect(w.find("input").attributes("type")).toBe("password");
        const botao = w.find("button");
        expect(botao.attributes("aria-label")).toBe("Mostrar senha");
        await botao.trigger("click");
        expect(w.find("input").attributes("type")).toBe("text");
        expect(w.find("input").element.value).toBe("segredo");
        expect(w.find("button").attributes("aria-label")).toBe("Ocultar senha");
    });
});
