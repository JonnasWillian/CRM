import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import AppPagination from '@/Components/ui/AppPagination.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import FilterChips from '@/Components/ui/FilterChips.vue';
import PeriodoSelector from '@/Components/ui/PeriodoSelector.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { on: () => () => {}, visit: vi.fn() },
}));

const meta = (parcial = {}) => ({ current_page: 2, last_page: 50, per_page: 25, total: 1243, from: 26, to: 50, ...parcial });

describe('AppPagination', () => {
    it('mostra o intervalo, páginas com elipse e emite página e itens por página', async () => {
        const w = mount(AppPagination, { props: { meta: meta() } });
        expect(w.text()).toContain('26–50 de 1.243');
        const paginas = w.findAll('[data-pagina]').map((b) => b.text());
        expect(paginas).toEqual(['1', '2', '3', '…', '50']);
        await w.find('[data-pagina="3"]').trigger('click');
        expect(w.emitted('update:pagina')[0]).toEqual([3]);
        await w.find('select').setValue('100');
        expect(w.emitted('update:porPagina')[0]).toEqual([100]);
        expect(w.find('[aria-label="Página anterior"]').attributes('disabled')).toBeUndefined();
    });

    it('desabilita anterior na primeira página e próxima na última', () => {
        const w = mount(AppPagination, { props: { meta: meta({ current_page: 1, last_page: 1, from: 1, to: 3, total: 3 }) } });
        expect(w.find('[aria-label="Página anterior"]').attributes('disabled')).toBeDefined();
        expect(w.find('[aria-label="Próxima página"]').attributes('disabled')).toBeDefined();
        expect(w.text()).toContain('1–3 de 3');
    });
});

describe('DataTable', () => {
    const colunas = [
        { key: 'nome', rotulo: 'Lead', ordenavel: true, primaria: true },
        { key: 'telefone', rotulo: 'Telefone', ocultarAbaixo: 'sm' },
        { key: 'valor', rotulo: 'Valor', ordenavel: true, alinhar: 'direita', formatar: (v) => `R$ ${v}` },
    ];
    const linhas = [
        { id: 1, nome: 'Ana', telefone: '11', valor: 10 },
        { id: 2, nome: 'Bruno', telefone: '22', valor: 20 },
    ];
    const montar = (props = {}) =>
        mount(DataTable, { props: { id: 'teste', colunas, linhas, meta: meta({ total: 2, last_page: 1, current_page: 1, from: 1, to: 2 }), ordenacao: { por: 'nome', dir: 'asc' }, ...props }, attachTo: document.body });

    beforeEach(() => localStorage.clear());

    it('renderiza cabeçalhos com aria-sort, células formatadas e colunas responsivas', () => {
        const w = montar();
        const ths = w.findAll('th');
        expect(ths[0].attributes('aria-sort')).toBe('ascending');
        expect(ths[2].attributes('aria-sort')).toBe('none');
        expect(w.text()).toContain('R$ 10');
        expect(ths[1].classes()).toContain('hidden');
        expect(ths[1].classes()).toContain('sm:table-cell');
        w.unmount();
    });

    it('clicar no cabeçalho ordenável emite update:ordenacao alternando a direção', async () => {
        const w = montar();
        await w.find('th button[data-ordenar="nome"]').trigger('click');
        expect(w.emitted('update:ordenacao')[0]).toEqual([{ por: 'nome', dir: 'desc' }]);
        await w.find('th button[data-ordenar="valor"]').trigger('click');
        expect(w.emitted('update:ordenacao')[1]).toEqual([{ por: 'valor', dir: 'asc' }]);
        w.unmount();
    });

    it('seleção: caixa do cabeçalho marca todos (tri-state) e emite update:selecionadas', async () => {
        const w = montar({ selecionavel: true, selecionadas: [] });
        const caixas = w.findAll('input[type="checkbox"]');
        expect(caixas).toHaveLength(3);
        await caixas[1].setValue(true);
        expect(w.emitted('update:selecionadas')[0]).toEqual([[1]]);
        await w.setProps({ selecionadas: [1] });
        expect(caixas[0].element.indeterminate).toBe(true);
        await caixas[0].setValue(true);
        expect(w.emitted('update:selecionadas')[1]).toEqual([[1, 2]]);
        w.unmount();
    });

    it('teclado: setas movem o foco entre linhas e Enter emite linha-clique', async () => {
        const w = montar();
        const trs = w.findAll('tbody tr');
        trs[0].element.focus();
        await trs[0].trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(trs[1].element);
        await trs[1].trigger('keydown', { key: 'Enter' });
        expect(w.emitted('linha-clique')[0][0]).toEqual(linhas[1]);
        w.unmount();
    });

    it('carregando sem linhas mostra esqueleto; vazio mostra EmptyState; erro mostra tentar de novo', async () => {
        const vazio = montar({ linhas: [], carregando: true });
        expect(vazio.find('[aria-hidden="true"].animate-pulse').exists()).toBe(true);
        await vazio.setProps({ carregando: false, vazio: { titulo: 'Nenhum lead', descricao: 'Cadastre o primeiro.' } });
        expect(vazio.text()).toContain('Nenhum lead');
        await vazio.setProps({ erro: 'Não deu para carregar.' });
        expect(vazio.text()).toContain('Não deu para carregar.');
        await vazio.find('button[data-tentar]').trigger('click');
        expect(vazio.emitted('tentar-novamente')).toHaveLength(1);
        vazio.unmount();
    });

    it('menu de colunas esconde uma coluna e guarda a preferência', async () => {
        const w = montar();
        await w.find('button[aria-label="Colunas"]').trigger('click');
        await nextTick();
        const opcao = document.body.querySelector('[role="menu"] input[data-coluna="telefone"]');
        opcao.click();
        await nextTick();
        expect(JSON.parse(localStorage.getItem('ui.tabela.teste')).ocultas).toEqual(['telefone']);
        expect(w.findAll('th').map((t) => t.text())).not.toContain('Telefone');
        w.unmount();
    });
});

describe('filtros', () => {
    it('FilterChips único e múltiplo', async () => {
        const unico = mount(FilterChips, { props: { modelValue: 'todos', opcoes: [{ valor: 'todos', rotulo: 'Todos' }, { valor: 'ativos', rotulo: 'Ativos' }] } });
        expect(unico.find('[aria-pressed="true"]').text()).toBe('Todos');
        await unico.findAll('button')[1].trigger('click');
        expect(unico.emitted('update:modelValue')[0]).toEqual(['ativos']);

        const multi = mount(FilterChips, { props: { modelValue: [1], multiplo: true, opcoes: [{ valor: 1, rotulo: 'A', cor: '#2b6cb0' }, { valor: 2, rotulo: 'B' }] } });
        await multi.findAll('button')[1].trigger('click');
        expect(multi.emitted('update:modelValue')[0]).toEqual([[1, 2]]);
        await multi.findAll('button')[0].trigger('click');
        expect(multi.emitted('update:modelValue')[1]).toEqual([[]]);
    });

    it('PeriodoSelector emite preset e, em personalizado, de/até', async () => {
        const w = mount(PeriodoSelector, { props: { modelValue: { periodo: '30d', de: null, ate: null } } });
        expect(w.find('[aria-pressed="true"]').text()).toContain('30 dias');
        await w.findAll('button').find((b) => b.text().includes('Mês anterior')).trigger('click');
        expect(w.emitted('update:modelValue')[0]).toEqual([{ periodo: 'mes_anterior', de: null, ate: null }]);
        await w.setProps({ modelValue: { periodo: 'custom', de: '2026-01-01', ate: '2026-01-31' } });
        expect(w.findAll('input[type="date"]')).toHaveLength(2);
        await w.findAll('input[type="date"]')[1].setValue('2026-02-15');
        expect(w.emitted('update:modelValue').at(-1)).toEqual([{ periodo: 'custom', de: '2026-01-01', ate: '2026-02-15' }]);
    });

    it('FilterBar: busca com debounce, contador de ativos e limpar', async () => {
        vi.useFakeTimers();
        const w = mount(FilterBar, { props: { busca: '', ativos: 2, placeholderBusca: 'Buscar' } });
        await w.find('input[type="search"]').setValue('ana');
        expect(w.emitted('update:busca')).toBeUndefined();
        vi.advanceTimersByTime(300);
        expect(w.emitted('update:busca')[0]).toEqual(['ana']);
        const limpar = w.findAll('button').find((b) => b.text().includes('Limpar'));
        expect(limpar.text()).toContain('2');
        await limpar.trigger('click');
        expect(w.emitted('limpar')).toHaveLength(1);
        vi.useRealTimers();
    });
});
