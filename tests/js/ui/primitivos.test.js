import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppSkeleton from '@/Components/ui/AppSkeleton.vue';
import AppTooltip from '@/Components/ui/AppTooltip.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import KpiCard from '@/Components/ui/KpiCard.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ProgressRing from '@/Components/ui/ProgressRing.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import StatBar from '@/Components/ui/StatBar.vue';
import { Trash2 } from 'lucide-vue-next';

describe('AppButton', () => {
    it('renderiza o texto, a variante e o tamanho', () => {
        const w = mount(AppButton, { props: { variante: 'danger', tamanho: 'sm' }, slots: { default: 'Excluir' } });
        expect(w.text()).toBe('Excluir');
        expect(w.attributes('data-variante')).toBe('danger');
        expect(w.attributes('data-tamanho')).toBe('sm');
        expect(w.attributes('type')).toBe('button');
    });

    it('carregando desabilita, marca aria-busy e mantém o rótulo', () => {
        const w = mount(AppButton, { props: { carregando: true }, slots: { default: 'Salvar' } });
        expect(w.attributes('disabled')).toBeDefined();
        expect(w.attributes('aria-busy')).toBe('true');
        expect(w.text()).toContain('Salvar');
        expect(w.find('[data-spinner]').exists()).toBe(true);
    });

    it('vira link quando recebe as="a" e href', () => {
        const w = mount(AppButton, { props: { as: 'a', href: '/leads' }, slots: { default: 'Leads' } });
        expect(w.element.tagName).toBe('A');
        expect(w.attributes('href')).toBe('/leads');
    });
});

describe('IconButton e AppTooltip', () => {
    it('IconButton exige rótulo acessível e o mostra como tooltip ao passar o mouse', async () => {
        const w = mount(IconButton, { props: { icone: Trash2, rotulo: 'Excluir' }, attachTo: document.body });
        const botao = w.find('button');
        expect(botao.attributes('aria-label')).toBe('Excluir');
        expect(botao.attributes('title')).toBeUndefined();
        await botao.trigger('mouseover');
        const dica = document.body.querySelector('[role="tooltip"]');
        expect(dica?.textContent).toBe('Excluir');
        expect(botao.attributes('aria-describedby')).toBe(dica.id);
        await botao.trigger('mouseout');
        expect(document.body.querySelector('[role="tooltip"]')).toBeNull();
        w.unmount();
    });

    it('clicar no gatilho esconde a dica (o clique já respondeu)', async () => {
        const w = mount(IconButton, { props: { icone: Trash2, rotulo: 'Excluir' }, attachTo: document.body });
        await w.find('button').trigger('mouseover');
        expect(document.body.querySelector('[role="tooltip"]')).not.toBeNull();
        await w.find('button').trigger('click');
        expect(document.body.querySelector('[role="tooltip"]')).toBeNull();
        w.unmount();
    });

    it('AppTooltip aparece no foco por teclado', async () => {
        const w = mount(AppTooltip, { props: { texto: 'Abrir no WhatsApp' }, slots: { default: '<button>wa</button>' }, attachTo: document.body });
        await w.find('button').trigger('focusin');
        expect(document.body.querySelector('[role="tooltip"]')?.textContent).toBe('Abrir no WhatsApp');
        await w.find('button').trigger('focusout');
        expect(document.body.querySelector('[role="tooltip"]')).toBeNull();
        w.unmount();
    });
});

describe('AppBadge', () => {
    it('com tom usa o token; com cor do estágio aplica a regra de mistura', () => {
        const porTom = mount(AppBadge, { props: { tom: 'ok' }, slots: { default: 'Ganho' } });
        expect(porTom.attributes('data-tom')).toBe('ok');

        const porCor = mount(AppBadge, { props: { cor: '#2b6cb0' }, slots: { default: 'Em negociação' } });
        expect(porCor.attributes('style')).toContain('--badge-cor: #2b6cb0');
        expect(porCor.attributes('data-tom')).toBe('cor');
    });

    it('cor inválida cai no neutro', () => {
        const w = mount(AppBadge, { props: { cor: 'azul' }, slots: { default: 'X' } });
        expect(w.attributes('data-tom')).toBe('neutral');
    });
});

describe('AppAvatar', () => {
    it('mostra as iniciais e descreve o nome completo', () => {
        const w = mount(AppAvatar, { props: { nome: 'Ana Souza' } });
        expect(w.text()).toBe('AS');
        expect(w.attributes('aria-label')).toBe('Ana Souza');
    });
});

describe('AppAlert', () => {
    it('erro e aviso são role=alert; os demais, status; fechável emite fechar', async () => {
        const erro = mount(AppAlert, { props: { tom: 'danger', fechavel: true }, slots: { default: 'Falhou' } });
        expect(erro.attributes('role')).toBe('alert');
        await erro.find('button[aria-label="Fechar"]').trigger('click');
        expect(erro.emitted('fechar')).toHaveLength(1);

        const info = mount(AppAlert, { props: { tom: 'info' }, slots: { default: 'Ok' } });
        expect(info.attributes('role')).toBe('status');
    });
});

describe('cabeçalhos e estados', () => {
    it('PageHeader é um h1 com subtítulo e ações; SectionHeader é um h2', () => {
        const p = mount(PageHeader, { props: { titulo: 'Leads', subtitulo: '1.248 leads' }, slots: { acoes: '<button>Novo</button>' } });
        expect(p.find('h1').text()).toBe('Leads');
        expect(p.text()).toContain('1.248 leads');
        expect(p.find('button').text()).toBe('Novo');

        const s = mount(SectionHeader, { props: { titulo: 'Tarefas', contagem: 3 } });
        expect(s.find('h2').text()).toContain('Tarefas');
        expect(s.text()).toContain('3');
    });

    it('EmptyState mostra título, descrição e a ação do slot', () => {
        const w = mount(EmptyState, { props: { titulo: 'Nenhum lead', descricao: 'Cadastre o primeiro.' }, slots: { acao: '<button>Novo lead</button>' } });
        expect(w.text()).toContain('Nenhum lead');
        expect(w.text()).toContain('Cadastre o primeiro.');
        expect(w.find('button').exists()).toBe(true);
    });

    it('Skeleton é invisível para leitores de tela', () => {
        expect(mount(AppSkeleton).attributes('aria-hidden')).toBe('true');
    });
});

describe('KpiCard, StatBar e ProgressRing', () => {
    it('KpiCard mostra rótulo, valor formatado e sub-linha', () => {
        const w = mount(KpiCard, { props: { rotulo: 'Leads ativos', valor: 1248, sub: '+ 12 fechados' } });
        expect(w.text()).toContain('Leads ativos');
        expect(w.text()).toContain('1.248');
        expect(w.text()).toContain('+ 12 fechados');
    });

    it('StatBar desenha uma barra por item, proporcional ao maior', () => {
        const w = mount(StatBar, { props: { itens: [{ rotulo: 'A', valor: 50 }, { rotulo: 'B', valor: 25 }] } });
        const barras = w.findAll('[data-barra]');
        expect(barras).toHaveLength(2);
        expect(barras[0].attributes('style')).toContain('100%');
        expect(barras[1].attributes('style')).toContain('50%');
        expect(w.text()).toContain('A');
        expect(w.text()).toContain('25');
    });

    it('ProgressRing descreve o percentual', () => {
        const w = mount(ProgressRing, { props: { valor: 42, rotulo: 'Taxa de ganho' } });
        expect(w.attributes('role')).toBe('img');
        expect(w.attributes('aria-label')).toBe('Taxa de ganho: 42%');
        expect(w.text()).toContain('42%');
    });
});
