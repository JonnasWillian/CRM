import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import AppCheckbox from '@/Components/ui/AppCheckbox.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppSwitch from '@/Components/ui/AppSwitch.vue';
import AppTabs from '@/Components/ui/AppTabs.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import FormField from '@/Components/ui/FormField.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

describe('AppModal', () => {
    it('abre como diálogo rotulado pelo título e fecha com Esc', async () => {
        const w = mount(AppModal, { props: { aberto: true, titulo: 'Novo lead' }, slots: { default: '<input id="campo" />' }, attachTo: document.body });
        const dialog = w.find('dialog');
        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('open')).toBeDefined();
        const titulo = w.find('h2');
        expect(titulo.text()).toBe('Novo lead');
        expect(dialog.attributes('aria-labelledby')).toBe(titulo.attributes('id'));
        await dialog.trigger('keydown', { key: 'Escape' });
        expect(w.emitted('fechar')).toHaveLength(1);
        w.unmount();
    });

    it('clique no fundo fecha, exceto se persistente', async () => {
        const w = mount(AppModal, { props: { aberto: true, titulo: 'X' }, attachTo: document.body });
        await w.find('dialog').trigger('click');
        expect(w.emitted('fechar')).toHaveLength(1);
        w.unmount();

        const p = mount(AppModal, { props: { aberto: true, titulo: 'X', persistente: true }, attachTo: document.body });
        await p.find('dialog').trigger('click');
        expect(p.emitted('fechar')).toBeUndefined();
        p.unmount();
    });

    it('devolve o foco ao elemento que abriu', async () => {
        const gatilho = document.createElement('button');
        document.body.appendChild(gatilho);
        gatilho.focus();
        const w = mount(AppModal, { props: { aberto: false, titulo: 'X' }, slots: { default: '<input id="primeiro" />' }, attachTo: document.body });
        await w.setProps({ aberto: true });
        await nextTick();
        expect(document.activeElement).toBe(document.getElementById('primeiro'));
        await w.setProps({ aberto: false });
        await nextTick();
        expect(document.activeElement).toBe(gatilho);
        w.unmount();
        gatilho.remove();
    });
});

describe('ConfirmHost', () => {
    it('renderiza o pedido pendente e responde', async () => {
        const w = mount(ConfirmHost, { attachTo: document.body });
        const { confirmar } = useConfirm();
        const promessa = confirmar({ titulo: 'Excluir o lead Ana?', mensagem: 'Vai para a lixeira.', rotuloConfirmar: 'Excluir lead', tom: 'danger' });
        await nextTick();
        expect(w.text()).toContain('Excluir o lead Ana?');
        expect(w.text()).toContain('Vai para a lixeira.');
        const botao = w.findAll('button').find((b) => b.text() === 'Excluir lead');
        expect(botao.attributes('data-variante')).toBe('danger');
        await botao.trigger('click');
        await expect(promessa).resolves.toBe(true);
        w.unmount();
    });

    it('com requerDigitacao só libera o botão quando o texto confere', async () => {
        const w = mount(ConfirmHost, { attachTo: document.body });
        const { confirmar, responder } = useConfirm();
        confirmar({ titulo: 'Apagar de vez?', rotuloConfirmar: 'Apagar', requerDigitacao: 'APAGAR' });
        await nextTick();
        const botao = w.findAll('button').find((b) => b.text() === 'Apagar');
        expect(botao.attributes('disabled')).toBeDefined();
        await w.find('input').setValue('APAGAR');
        expect(botao.attributes('disabled')).toBeUndefined();
        responder(false);
        w.unmount();
    });
});

describe('ToastHost', () => {
    beforeEach(() => useToast().limpar());

    it('lista os toasts numa região viva, com fechar e ação', async () => {
        const w = mount(ToastHost, { attachTo: document.body });
        const { sucesso, erro } = useToast();
        const onClick = vi.fn();
        sucesso('Lead movido.', { acao: { rotulo: 'Desfazer', onClick } });
        erro('Falhou.');
        await nextTick();
        expect(w.find('[aria-live="polite"]').exists()).toBe(true);
        expect(w.text()).toContain('Lead movido.');
        expect(w.text()).toContain('Falhou.');
        await w.findAll('button').find((b) => b.text() === 'Desfazer').trigger('click');
        expect(onClick).toHaveBeenCalledOnce();
        expect(w.text()).not.toContain('Lead movido.');
        await w.find('button[aria-label="Fechar"]').trigger('click');
        expect(w.text()).not.toContain('Falhou.');
        w.unmount();
    });
});

describe('FormField + campos', () => {
    it('liga rótulo, campo, dica e erro por id e aria', () => {
        const w = mount({
            components: { FormField, AppInput },
            template: `<FormField rotulo="E-mail" obrigatorio dica="Usado no login" :erro="['Inválido', 'Curto']"><AppInput model-value="" /></FormField>`,
        });
        const input = w.find('input');
        const label = w.find('label');
        expect(label.attributes('for')).toBe(input.attributes('id'));
        expect(label.text()).toContain('E-mail');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-required')).toBe('true');
        const erro = w.find('[role="alert"]');
        expect(erro.text()).toBe('Inválido');
        expect(input.attributes('aria-describedby').split(' ')).toContain(erro.attributes('id'));
        expect(w.text()).toContain('Usado no login');
    });

    it('AppInput e AppTextarea emitem update:modelValue', async () => {
        const i = mount(AppInput, { props: { modelValue: '' } });
        await i.find('input').setValue('ana');
        expect(i.emitted('update:modelValue')[0]).toEqual(['ana']);

        const t = mount(AppTextarea, { props: { modelValue: '', maxlength: 10, mostrarContador: true } });
        await t.find('textarea').setValue('olá');
        expect(t.emitted('update:modelValue')[0]).toEqual(['olá']);
        await t.setProps({ modelValue: 'olá' });
        expect(t.text()).toContain('3/10');
    });

    it('AppMoneyInput lê "1.500,00" como 1500 e mostra formatado ao sair do campo', async () => {
        const w = mount(AppMoneyInput, { props: { modelValue: null } });
        const input = w.find('input');
        await input.setValue('1.500,00');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([1500]);
        await input.setValue('');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([null]);
        await w.setProps({ modelValue: 1234.5 });
        await input.trigger('blur');
        expect(input.element.value).toBe('1.234,50');
    });

    it('AppSelect, AppCheckbox e AppSwitch', async () => {
        const s = mount(AppSelect, { props: { modelValue: '', opcoes: [{ valor: 1, rotulo: 'Um' }, { valor: 2, rotulo: 'Dois' }], placeholder: 'Escolha' } });
        expect(s.findAll('option').map((o) => o.text())).toEqual(['Escolha', 'Um', 'Dois']);
        await s.find('select').setValue('2');
        expect(s.emitted('update:modelValue')[0]).toEqual([2]);

        const c = mount(AppCheckbox, { props: { modelValue: false, rotulo: 'Ativo', indeterminado: true } });
        expect(c.find('input').element.indeterminate).toBe(true);
        await c.find('input').setValue(true);
        expect(c.emitted('update:modelValue')[0]).toEqual([true]);

        const sw = mount(AppSwitch, { props: { modelValue: false, rotulo: 'Parcelado' } });
        const botao = sw.find('[role="switch"]');
        expect(botao.attributes('aria-checked')).toBe('false');
        await botao.trigger('click');
        expect(sw.emitted('update:modelValue')[0]).toEqual([true]);
    });
});

describe('AppDropdown', () => {
    const montar = () =>
        mount(
            {
                components: { AppDropdown, AppDropdownItem },
                template: `
                    <AppDropdown rotulo="Mais ações">
                        <AppDropdownItem rotulo="Abrir" @click="aberto = true" />
                        <AppDropdownItem rotulo="Excluir" tom="danger" />
                    </AppDropdown>`,
                data: () => ({ aberto: false }),
            },
            { attachTo: document.body },
        );

    it('abre no clique, navega por setas e fecha com Esc devolvendo o foco', async () => {
        const w = montar();
        const gatilho = w.find('button[aria-haspopup="menu"]');
        expect(gatilho.attributes('aria-expanded')).toBe('false');
        await gatilho.trigger('click');
        await nextTick();
        const menu = document.body.querySelector('[role="menu"]');
        expect(menu).not.toBeNull();
        expect(gatilho.attributes('aria-expanded')).toBe('true');
        const itens = menu.querySelectorAll('[role="menuitem"]');
        expect(itens).toHaveLength(2);
        expect(document.activeElement).toBe(itens[0]);
        menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        expect(document.activeElement).toBe(itens[1]);
        menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();
        expect(document.body.querySelector('[role="menu"]')).toBeNull();
        expect(document.activeElement).toBe(gatilho.element);
        w.unmount();
    });

    it('escolher um item executa a ação e fecha', async () => {
        const w = montar();
        await w.find('button[aria-haspopup="menu"]').trigger('click');
        await nextTick();
        document.body.querySelector('[role="menuitem"]').click();
        await nextTick();
        expect(w.vm.aberto).toBe(true);
        expect(document.body.querySelector('[role="menu"]')).toBeNull();
        w.unmount();
    });
});

describe('AppTabs', () => {
    const abas = [
        { chave: 'atividades', rotulo: 'Atividades' },
        { chave: 'tarefas', rotulo: 'Tarefas', contagem: 2 },
        { chave: 'dados', rotulo: 'Dados' },
    ];

    it('marca a aba ativa, troca no clique e por setas, e sincroniza a URL', async () => {
        history.replaceState(null, '', '/leads/1');
        const w = mount(AppTabs, { props: { modelValue: 'atividades', abas, sincronizarUrl: 'aba' } });
        const tabs = w.findAll('[role="tab"]');
        expect(tabs[0].attributes('aria-selected')).toBe('true');
        expect(tabs[1].text()).toContain('2');
        await tabs[1].trigger('click');
        expect(w.emitted('update:modelValue')[0]).toEqual(['tarefas']);
        expect(location.search).toBe('?aba=tarefas');
        await w.setProps({ modelValue: 'tarefas' });
        await tabs[1].trigger('keydown', { key: 'ArrowRight' });
        expect(w.emitted('update:modelValue')[1]).toEqual(['dados']);
    });
});
