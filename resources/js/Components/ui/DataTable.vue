<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ArrowUpDown, Columns3, Rows3, Rows4 } from 'lucide-vue-next';
import { usePrefsLocais } from '@/Composables/usePrefsLocais';
import AppAlert from './AppAlert.vue';
import AppDropdown from './AppDropdown.vue';
import AppPagination from './AppPagination.vue';
import EmptyState from './EmptyState.vue';
import SkeletonRows from './SkeletonRows.vue';

/**
 * Grade do app inteiro, dirigida pelo servidor: ordenação, página e itens
 * por página são eventos; os dados chegam prontos (`linhas` + `meta`).
 *
 * - <table> semântica; cabeçalho ordenável com aria-sort; coluna primária
 *   fixa à esquerda no scroll horizontal; colunas `ocultarAbaixo` somem no
 *   breakpoint e a primária mostra sub-linhas.
 * - Linhas navegáveis por teclado (↑/↓, Enter/Espaço); seleção com caixa
 *   tri-state no cabeçalho e Shift+clique para intervalo.
 * - Visibilidade de colunas e densidade guardadas por `id` no navegador.
 * - Esqueleto quando não há linhas; opacidade reduzida quando há.
 */
const props = defineProps({
    id: { type: String, required: true },
    colunas: { type: Array, required: true }, // [{ key, rotulo, ordenavel?, alinhar?, largura?, ocultarAbaixo?, formatar?, primaria?, sempreVisivel? }]
    linhas: { type: Array, default: () => [] },
    chave: { type: String, default: 'id' },
    meta: { type: Object, default: null },
    ordenacao: { type: Object, default: null }, // { por, dir }
    selecionavel: { type: Boolean, default: false },
    selecionadas: { type: Array, default: () => [] },
    totalResultados: { type: Number, default: null },
    carregando: { type: Boolean, default: false },
    erro: { type: String, default: null },
    hrefDaLinha: { type: Function, default: null },
    linhasClicaveis: { type: Boolean, default: true },
    vazio: { type: Object, default: () => ({ titulo: 'Nada por aqui', descricao: null, icone: null }) },
    opcoesPorPagina: { type: Array, default: () => [25, 50, 100] },
    densidadePadrao: { type: String, default: 'confortavel' }, // confortavel | compacta
    cabecalhoFixo: { type: Boolean, default: true },
    alturaMaxima: { type: String, default: 'calc(100dvh - 240px)' },
    rotuloDaLinha: { type: Function, default: null },
});

const emit = defineEmits(['update:ordenacao', 'update:pagina', 'update:porPagina', 'update:selecionadas', 'selecionar-todos', 'linha-clique', 'tentar-novamente']);

const prefs = usePrefsLocais(`tabela.${props.id}`, { ocultas: [], densidade: props.densidadePadrao });

const colunaPrimaria = computed(() => props.colunas.find((c) => c.primaria) ?? props.colunas[0]);
const visiveis = computed(() => props.colunas.filter((c) => c === colunaPrimaria.value || c.sempreVisivel || !prefs.value.ocultas.includes(c.key)));
const ocultaveis = computed(() => props.colunas.filter((c) => c !== colunaPrimaria.value && !c.sempreVisivel));
const compacta = computed(() => prefs.value.densidade === 'compacta');

const RESPONSIVO = { sm: 'hidden sm:table-cell', md: 'hidden md:table-cell', lg: 'hidden lg:table-cell', xl: 'hidden xl:table-cell' };
const ALINHAR = { direita: 'text-right', centro: 'text-center', esquerda: 'text-left' };

const classesDaColuna = (c) => [RESPONSIVO[c.ocultarAbaixo] ?? '', ALINHAR[c.alinhar] ?? 'text-left'];
const valor = (linha, c) => (c.formatar ? c.formatar(linha[c.key], linha) : linha[c.key]);
const chaveDe = (linha) => linha[props.chave];

// ── Ordenação ──
const ariaSort = (c) => {
    if (!c.ordenavel) return undefined;
    if (props.ordenacao?.por !== c.key) return 'none';
    return props.ordenacao.dir === 'desc' ? 'descending' : 'ascending';
};
function ordenar(c) {
    if (!c.ordenavel || props.carregando) return;
    const dir = props.ordenacao?.por === c.key && props.ordenacao.dir === 'asc' ? 'desc' : 'asc';
    emit('update:ordenacao', { por: c.key, dir });
}

// ── Seleção ──
const cabecalho = ref(null);
const todasSelecionadas = computed(() => props.linhas.length > 0 && props.linhas.every((l) => props.selecionadas.includes(chaveDe(l))));
const algumaSelecionada = computed(() => props.linhas.some((l) => props.selecionadas.includes(chaveDe(l))));
let ultimaMarcada = null;
let comShift = false;

function alternarTodas(e) {
    const chaves = props.linhas.map(chaveDe);
    emit('update:selecionadas', e.target.checked ? [...new Set([...props.selecionadas, ...chaves])] : props.selecionadas.filter((k) => !chaves.includes(k)));
}

// O Shift vem do clique; a marcação, do change (teclado também dispara change).
function alternarLinha(linha, e) {
    const k = chaveDe(linha);
    const proximas = new Set(props.selecionadas);
    if (comShift && ultimaMarcada !== null) {
        const chaves = props.linhas.map(chaveDe);
        const [a, b] = [chaves.indexOf(ultimaMarcada), chaves.indexOf(k)].sort((x, y) => x - y);
        for (const c of chaves.slice(a, b + 1)) e.target.checked ? proximas.add(c) : proximas.delete(c);
    } else {
        e.target.checked ? proximas.add(k) : proximas.delete(k);
    }
    ultimaMarcada = k;
    emit('update:selecionadas', [...proximas]);
}

const indeterminado = (el) => { if (el) el.indeterminate = algumaSelecionada.value && !todasSelecionadas.value; };

// ── Teclado nas linhas ──
const corpo = ref(null);
function aoTeclarLinha(e, linha, i) {
    const trs = corpo.value?.querySelectorAll('tr[tabindex]') ?? [];
    if (e.key === 'ArrowDown') { e.preventDefault(); trs[i + 1]?.focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); trs[i - 1]?.focus(); }
    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); emit('linha-clique', linha); }
}

function aoClicarLinha(e, linha) {
    if (!props.linhasClicaveis) return;
    if (e.target.closest('a, button, input, select, textarea, [role="menu"], label')) return;
    emit('linha-clique', linha);
}

// ── Colunas e densidade ──
function alternarColuna(key) {
    const ocultas = new Set(prefs.value.ocultas);
    ocultas.has(key) ? ocultas.delete(key) : ocultas.add(key);
    prefs.value = { ...prefs.value, ocultas: [...ocultas] };
}
const restaurarColunas = () => { prefs.value = { ...prefs.value, ocultas: [] }; };
const alternarDensidade = () => { prefs.value = { ...prefs.value, densidade: compacta.value ? 'confortavel' : 'compacta' }; };

const total = computed(() => props.totalResultados ?? props.meta?.total ?? props.linhas.length);
const temSelecao = computed(() => props.selecionavel && props.selecionadas.length > 0);
const alturaLinha = computed(() => (compacta.value ? 'h-10' : 'h-12'));
</script>

<template>
    <div class="flex flex-col rounded-lg border border-line bg-bg-1 shadow-1">
        <!-- Barra: contagem / ações em massa / colunas e densidade -->
        <div class="flex min-h-[52px] flex-wrap items-center gap-2 border-b border-line px-3 py-2" :class="temSelecao ? 'bg-primary-subtle' : ''">
            <template v-if="temSelecao">
                <span class="num text-sm font-medium text-primary">{{ selecionadas.length }} selecionado{{ selecionadas.length === 1 ? '' : 's' }}</span>
                <button
                    v-if="totalResultados !== null && selecionadas.length < totalResultados"
                    type="button"
                    class="text-sm font-medium text-primary underline-offset-2 hover:underline"
                    @click="emit('selecionar-todos')"
                >
                    Selecionar todos os {{ Number(totalResultados).toLocaleString('pt-BR') }} resultados
                </button>
                <div class="flex flex-wrap items-center gap-2"><slot name="acoes-em-massa" :selecionadas="selecionadas" :limpar="() => emit('update:selecionadas', [])" /></div>
                <button type="button" class="ml-auto text-sm text-fg-1 hover:text-fg-0" @click="emit('update:selecionadas', [])">Limpar seleção</button>
            </template>
            <template v-else>
                <p class="num text-sm text-fg-2"><span class="font-medium text-fg-0">{{ Number(total).toLocaleString('pt-BR') }}</span> {{ total === 1 ? 'resultado' : 'resultados' }}</p>
                <div class="flex items-center gap-2"><slot name="barra" /></div>
                <div class="ml-auto flex items-center gap-1">
                    <button type="button" :aria-label="compacta ? 'Linhas confortáveis' : 'Linhas compactas'" :aria-pressed="compacta ? 'true' : 'false'" class="dt-icone" @click="alternarDensidade">
                        <component :is="compacta ? Rows3 : Rows4" :size="16" aria-hidden="true" />
                    </button>
                    <AppDropdown v-if="ocultaveis.length" rotulo="Colunas" :icone="Columns3" alinhar="end">
                        <p class="px-2.5 pb-1 pt-1.5 text-xs font-medium uppercase tracking-wider text-fg-2">Colunas visíveis</p>
                        <label v-for="c in ocultaveis" :key="c.key" role="menuitemcheckbox" :aria-checked="!prefs.ocultas.includes(c.key)" class="flex cursor-pointer items-center gap-2.5 rounded px-2.5 py-2 text-md text-fg-0 hover:bg-bg-2">
                            <input type="checkbox" :data-coluna="c.key" :checked="!prefs.ocultas.includes(c.key)" class="form-checkbox h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-focus/25" @change="alternarColuna(c.key)" />
                            {{ c.rotulo }}
                        </label>
                        <div role="separator" class="my-1 h-px bg-line" />
                        <button type="button" role="menuitem" tabindex="-1" class="w-full rounded px-2.5 py-2 text-left text-md text-fg-1 hover:bg-bg-2" @click="restaurarColunas">Restaurar padrão</button>
                    </AppDropdown>
                </div>
            </template>
        </div>

        <!-- Erro de carga -->
        <div v-if="erro" class="p-4">
            <AppAlert tom="danger" titulo="Não deu para carregar">
                {{ erro }}
                <template #acao>
                    <button type="button" data-tentar class="rounded border border-line bg-bg-1 px-3 py-1.5 text-sm font-medium text-fg-0 hover:bg-bg-2" @click="emit('tentar-novamente')">Tentar de novo</button>
                </template>
            </AppAlert>
        </div>

        <!-- Esqueleto -->
        <SkeletonRows v-else-if="carregando && linhas.length === 0" :linhas="Math.min(meta?.per_page ?? 10, 10)" :colunas="Math.min(visiveis.length, 5)" />

        <!-- Vazio -->
        <EmptyState v-else-if="linhas.length === 0" :titulo="vazio.titulo" :descricao="vazio.descricao" :icone="vazio.icone">
            <template v-if="$slots.vazio" #acao><slot name="vazio" /></template>
        </EmptyState>

        <!-- Tabela -->
        <div
            v-else
            class="dt-rolagem overflow-auto"
            :class="carregando ? 'opacity-60' : ''"
            :style="cabecalhoFixo ? { '--dt-altura': alturaMaxima } : undefined"
            :aria-busy="carregando ? 'true' : undefined"
        >
            <table class="w-full border-collapse text-md">
                <thead class="bg-bg-0 text-xs uppercase tracking-wider text-fg-2" :class="cabecalhoFixo ? 'sticky top-0 z-10' : ''">
                    <tr>
                        <th v-if="selecionavel" scope="col" class="w-10 px-3 py-2.5">
                            <input ref="cabecalho" type="checkbox" :checked="todasSelecionadas" :indeterminate.prop="algumaSelecionada && !todasSelecionadas" aria-label="Selecionar todos desta página" class="form-checkbox h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-focus/25" @change="alternarTodas" />
                        </th>
                        <th
                            v-for="c in visiveis"
                            :key="c.key"
                            scope="col"
                            :aria-sort="ariaSort(c)"
                            class="whitespace-nowrap px-3 py-2.5 font-medium"
                            :class="[classesDaColuna(c), c === colunaPrimaria ? 'sticky left-0 z-10 bg-bg-0' : '']"
                            :style="c.largura ? { width: c.largura } : undefined"
                        >
                            <button v-if="c.ordenavel" type="button" :data-ordenar="c.key" class="inline-flex items-center gap-1 rounded hover:text-fg-0" :class="ordenacao?.por === c.key ? 'text-fg-0' : ''" @click="ordenar(c)">
                                {{ c.rotulo }}
                                <ArrowUp v-if="ordenacao?.por === c.key && ordenacao.dir === 'asc'" :size="12" aria-hidden="true" />
                                <ArrowDown v-else-if="ordenacao?.por === c.key" :size="12" aria-hidden="true" />
                                <ArrowUpDown v-else :size="12" class="opacity-50" aria-hidden="true" />
                            </button>
                            <span v-else>{{ c.rotulo }}</span>
                        </th>
                        <th v-if="$slots.acoes" scope="col" class="w-px px-3 py-2.5 text-right font-medium"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody ref="corpo" class="divide-y divide-line">
                    <tr
                        v-for="(linha, i) in linhas"
                        :key="chaveDe(linha)"
                        :tabindex="linhasClicaveis ? 0 : undefined"
                        :aria-selected="selecionavel ? selecionadas.includes(chaveDe(linha)) : undefined"
                        class="group transition-colors duration-1 ease-ui hover:bg-bg-2/60 focus:outline-none focus-visible:bg-primary-subtle/60"
                        :class="[alturaLinha, linhasClicaveis ? 'cursor-pointer' : '', selecionadas.includes(chaveDe(linha)) ? 'bg-primary-subtle/50' : '']"
                        @click="aoClicarLinha($event, linha)"
                        @keydown="aoTeclarLinha($event, linha, i)"
                    >
                        <td v-if="selecionavel" class="px-3">
                            <input type="checkbox" :checked="selecionadas.includes(chaveDe(linha))" :aria-label="`Selecionar ${rotuloDaLinha ? rotuloDaLinha(linha) : (linha[colunaPrimaria.key] ?? chaveDe(linha))}`" class="form-checkbox h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-focus/25" @click="comShift = $event.shiftKey" @change="alternarLinha(linha, $event)" />
                        </td>
                        <td
                            v-for="c in visiveis"
                            :key="c.key"
                            class="px-3 text-fg-0"
                            :class="[classesDaColuna(c), c === colunaPrimaria ? 'sticky left-0 z-[1] bg-bg-1 group-hover:bg-bg-2' : '', compacta ? 'py-1.5' : 'py-2.5']"
                        >
                            <slot :name="`celula-${c.key}`" :linha="linha" :valor="linha[c.key]">
                                <component :is="hrefDaLinha && c === colunaPrimaria ? Link : 'span'" :href="hrefDaLinha && c === colunaPrimaria ? hrefDaLinha(linha) : undefined" :class="hrefDaLinha && c === colunaPrimaria ? 'font-medium text-fg-0 hover:text-primary' : ''">
                                    {{ valor(linha, c) ?? '—' }}
                                </component>
                            </slot>
                        </td>
                        <td v-if="$slots.acoes" class="px-2 text-right">
                            <div class="flex items-center justify-end gap-0.5"><slot name="acoes" :linha="linha" /></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && !erro && linhas.length > 0" class="border-t border-line px-3 py-3">
            <AppPagination :meta="meta" :opcoes-por-pagina="opcoesPorPagina" :carregando="carregando" @update:pagina="emit('update:pagina', $event)" @update:por-pagina="emit('update:porPagina', $event)" />
        </div>
    </div>
</template>

<style scoped>
.dt-icone {
    display: inline-flex; align-items: center; justify-content: center;
    height: 36px; width: 36px; border-radius: var(--r-2); color: var(--fg-1);
    transition: background var(--d-1) var(--e), color var(--d-1) var(--e);
}
.dt-icone:hover, .dt-icone[aria-pressed='true'] { background: var(--bg-2); color: var(--fg-0); }
@media (min-width: 1024px) {
    .dt-rolagem { max-height: var(--dt-altura, none); }
}
</style>
