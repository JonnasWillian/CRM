import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const formPost = vi.fn();
const routerPost = vi.fn();
let ultimoForm = null;

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { app: { nome: 'UserFlow' }, auth: { user: { name: 'Ana Souza' } } } }),
    useForm: (dados) => {
        ultimoForm = reactive({
            ...dados,
            errors: {},
            processing: false,
            transform(fn) { this._transform = fn; return this; },
            post: (...a) => formPost(...a),
        });
        return ultimoForm;
    },
    router: { post: (...a) => routerPost(...a) },
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

globalThis.route = (nome) => `/${nome}`;

const { default: Questionario } = await import('@/Pages/BoasVindas/Questionario.vue');

const op = (...pares) => pares.map(([valor, rotulo]) => ({ valor, rotulo }));
const OPCOES = {
    experiencias: op(['varios', 'Já usei vários CRMs e conheço bem'], ['alguns', 'Já usei um ou outro no dia a dia'], ['nunca', 'Nunca usei ou uso pouco']),
    cargos: op(['dono', 'Dono(a) ou sócio(a)'], ['vendedor', 'Vendedor(a)']),
    ramos: op(['software', 'Software e tecnologia'], ['outro', 'Outro']),
    tamanhos: op(['1', '1 pessoa'], ['6-10', '6 a 10 pessoas']),
    para_quem: op(['empresas', 'Empresas'], ['pessoas', 'Pessoas']),
    canais: op(['whatsapp', 'WhatsApp'], ['email', 'E-mail']),
    origens: op(['indicacao', 'Indicação'], ['site', 'Site']),
    conheceu: op(['google', 'Google']),
};

const montar = () => mount(Questionario, { props: { opcoes: OPCOES, nome: 'Ana' }, global: { mocks: { route: globalThis.route } } });
const botao = (w, texto) => w.findAll('button').find((b) => b.text().trim() === texto);
const prosseguir = (w) => botao(w, 'Prosseguir').trigger('click');

async function responderAte4(w) {
    await botao(w, 'Nunca usei ou uso pouco').trigger('click');
    await w.find('select#pergunta-cargo').setValue('dono');
    await prosseguir(w);
    await w.find('select#pergunta-ramo').setValue('software');
    await botao(w, '6 a 10 pessoas').trigger('click');
    await prosseguir(w);
    await w.find('select#pergunta-para-quem').setValue('empresas');
    await botao(w, 'WhatsApp').trigger('click');
    await botao(w, 'E-mail').trigger('click');
    await botao(w, 'Indicação').trigger('click');
    await prosseguir(w);
}

describe('Questionário de primeiro acesso', () => {
    beforeEach(() => {
        formPost.mockClear();
        routerPost.mockClear();
    });

    it('mostra o passo 1 de 4 com experiência (cartões) e cargo', () => {
        const w = montar();
        expect(w.text()).toContain('Passo 1 de 4');
        expect(w.text()).toContain('Qual sua experiência com CRM?');
        expect(w.findAll('[role="radio"]').map((r) => r.text())).toEqual(OPCOES.experiencias.map((o) => o.rotulo));
        expect(w.find('select#pergunta-cargo').exists()).toBe(true);
    });

    it('Prosseguir sem responder mostra o erro e não avança', async () => {
        const w = montar();
        await prosseguir(w);
        expect(w.text()).toContain('Conte sua experiência com CRM.');
        expect(w.text()).toContain('Informe o seu cargo.');
        expect(w.text()).toContain('Passo 1 de 4');
    });

    it('navega até o passo 4, volta com Voltar e envia tudo ao concluir', async () => {
        const w = montar();
        await responderAte4(w);
        expect(w.text()).toContain('Passo 4 de 4');

        await botao(w, 'Voltar').trigger('click');
        expect(w.text()).toContain('Passo 3 de 4');
        await prosseguir(w);
        await botao(w, 'Concluir').trigger('click');

        expect(formPost).toHaveBeenCalledWith('/boas-vindas.store', expect.any(Object));
        expect(ultimoForm.experiencia).toBe('nunca');
        expect(ultimoForm.ramo).toBe('software');
        expect(ultimoForm.tamanho).toBe('6-10');
        expect(ultimoForm.canais).toEqual(['whatsapp', 'email']);
        expect(ultimoForm.origens).toEqual(['indicacao']);
        // Campos opcionais vazios vão como null.
        expect(ultimoForm._transform({ ...ultimoForm, conheceu: '', telefone: '' })).toMatchObject({ conheceu: null, telefone: null });
    });

    it('chips de canais e origens aceitam várias escolhas e desmarcam no segundo clique', async () => {
        const w = montar();
        await botao(w, 'Já usei vários CRMs e conheço bem').trigger('click');
        await w.find('select#pergunta-cargo').setValue('vendedor');
        await prosseguir(w);
        await w.find('select#pergunta-ramo').setValue('outro');
        await botao(w, '1 pessoa').trigger('click');
        await prosseguir(w);

        await botao(w, 'WhatsApp').trigger('click');
        await botao(w, 'E-mail').trigger('click');
        await botao(w, 'WhatsApp').trigger('click');
        await botao(w, 'Indicação').trigger('click');
        await botao(w, 'Site').trigger('click');

        expect(ultimoForm.canais).toEqual(['email']);
        expect(ultimoForm.origens).toEqual(['indicacao', 'site']);
    });

    it('Pular e usar o padrão chama a rota de pular', async () => {
        const w = montar();
        await botao(w, 'Pular e usar o padrão').trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/boas-vindas.pular');
    });
});
