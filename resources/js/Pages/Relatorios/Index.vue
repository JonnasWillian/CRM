<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, CircleDollarSign, CircleX, TrendingUp, UserPlus, Wallet } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import KpiCard from '@/Components/ui/KpiCard.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PeriodoSelector from '@/Components/ui/PeriodoSelector.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import StatBar from '@/Components/ui/StatBar.vue';
import AbasDeRelatorio from '@/Components/relatorios/AbasDeRelatorio.vue';
import FaturamentoProjecao from '@/Components/relatorios/FaturamentoProjecao.vue';
import { formatBRL, parseDataLocal } from '@/utils/formatadores';

/** Visão geral: números do período, comparação entre vendedores e ganhos dos últimos 12 meses. */
const props = defineProps({
    relatorio: { type: Object, required: true }, // { metricas, por_vendedor[], ganhos_por_mes[] }
    periodo: { type: Object, required: true },
    agentes: { type: Array, default: () => [] },
    funis: { type: Array, required: true },
});

const filtros = ref({ ...props.periodo });
const carregando = ref(false);
function aplicar(parcial) {
    filtros.value = { ...filtros.value, ...parcial };
    const f = filtros.value;
    if (f.periodo === 'custom' && (!f.de || !f.ate)) return;
    const query = {};
    if (f.periodo && f.periodo !== '30d') query.periodo = f.periodo;
    if (f.periodo === 'custom') { query.de = f.de; query.ate = f.ate; }
    if (f.user_id) query.user_id = f.user_id;
    if (f.funil_id) query.funil_id = f.funil_id;
    router.get(route('relatorios.index'), query, { only: ['relatorio', 'periodo'], preserveState: true, preserveScroll: true, replace: true, onStart: () => { carregando.value = true; }, onFinish: () => { carregando.value = false; } });
}
const periodoModel = computed({ get: () => ({ periodo: filtros.value.periodo, de: filtros.value.de, ate: filtros.value.ate }), set: (v) => aplicar(v) });

const m = computed(() => props.relatorio.metricas);
const vazio = computed(() => m.value.fluxo.leads_criados === 0 && m.value.fluxo.ganhos.total === 0 && m.value.fluxo.perdidos.total === 0);

// ── Por vendedor (ordenável na tela: a lista é pequena) ──
const ordem = ref({ por: 'valor_ganho', dir: 'desc' });
const linhas = computed(() => [...props.relatorio.por_vendedor].sort((a, b) => {
    const [x, y] = [a[ordem.value.por] ?? -1, b[ordem.value.por] ?? -1];
    const r = typeof x === 'string' ? x.localeCompare(y, 'pt-BR') : x - y;
    return ordem.value.dir === 'asc' ? r : -r;
}));
const colunas = [
    { key: 'nome', rotulo: 'Vendedor', ordenavel: true, primaria: true },
    { key: 'leads_ativos', rotulo: 'Ativos', ordenavel: true, alinhar: 'direita' },
    { key: 'leads_criados', rotulo: 'Novos', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'sm' },
    { key: 'ganhos', rotulo: 'Ganhos', ordenavel: true, alinhar: 'direita' },
    { key: 'valor_ganho', rotulo: 'Valor ganho', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'md', formatar: (v) => formatBRL(v) },
    { key: 'perdidos', rotulo: 'Perdidos', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'sm' },
    { key: 'taxa_ganho', rotulo: 'Taxa', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'md', formatar: (v) => (v === null ? '—' : `${v.toLocaleString('pt-BR')}%`) },
    { key: 'mrr', rotulo: 'Recorrência/mês', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'md', formatar: (v) => formatBRL(v) },
    { key: 'recorrente_a_realizar', rotulo: 'Recorr. a realizar', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'xl', formatar: (v) => formatBRL(v) },
    { key: 'valor_ponderado', rotulo: 'Previsão pond.', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'lg', formatar: (v) => formatBRL(v) },
];

const meses = computed(() => props.relatorio.ganhos_por_mes.map((x) => ({ rotulo: parseDataLocal(`${x.mes}-01`)?.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '') ?? x.mes, valor: x.valor })));
const totalAno = computed(() => props.relatorio.ganhos_por_mes.reduce((s, x) => s + x.valor, 0));
const periodoQuery = computed(() => ({ de: m.value.periodo.inicio, ate: m.value.periodo.fim }));
</script>

<template>
    <Head title="Relatórios" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Relatórios" :subtitulo="m.periodo.rotulo">
                <template #abaixo>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <PeriodoSelector v-model="periodoModel" />
                        <AppSelect v-if="agentes.length" :model-value="filtros.user_id" :opcoes="agentes.map((a) => ({ valor: a.id, rotulo: a.name }))" placeholder="Toda a equipe" tamanho="sm" aria-label="Vendedor" class="w-44" @update:model-value="aplicar({ user_id: $event })" />
                        <AppSelect v-if="funis.length > 1" :model-value="filtros.funil_id" :opcoes="funis.map((f) => ({ valor: f.id, rotulo: f.nome }))" placeholder="Todos os funis" tamanho="sm" aria-label="Funil" class="w-44" @update:model-value="aplicar({ funil_id: $event })" />
                    </div>
                </template>
            </PageHeader>
            <AbasDeRelatorio atual="relatorios.index" />

            <div :class="carregando ? 'opacity-60' : ''" :aria-busy="carregando ? 'true' : undefined">
                <AppCard v-if="vazio" class="mb-4">
                    <EmptyState titulo="Nenhum dado no período" descricao="Amplie o período ou mude os filtros." tamanho="sm" />
                </AppCard>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <KpiCard rotulo="Leads criados" :valor="m.fluxo.leads_criados" :icone="UserPlus" contar :href="route('leads.index', periodoQuery)" />
                    <KpiCard rotulo="Ganhos" :valor="m.fluxo.ganhos.total" :sub="formatBRL(m.fluxo.ganhos.valor)" :icone="CircleCheck" tom="ok" contar />
                    <KpiCard rotulo="Perdidos" :valor="m.fluxo.perdidos.total" :sub="formatBRL(m.fluxo.perdidos.valor)" :icone="CircleX" tom="danger" contar :href="route('relatorios.perdas')" />
                    <KpiCard rotulo="Taxa de ganho" :valor="m.fluxo.taxa_ganho === null ? '—' : `${m.fluxo.taxa_ganho.toLocaleString('pt-BR')}%`" :icone="TrendingUp" />
                    <KpiCard rotulo="Ticket médio" :valor="m.fluxo.ticket_medio === null ? '—' : formatBRL(m.fluxo.ticket_medio)" :icone="CircleDollarSign" />
                    <KpiCard rotulo="Previsão ponderada" :valor="formatBRL(m.forecast.valor_ponderado)" :sub="`${formatBRL(m.forecast.valor_estimado_aberto)} sem ponderar`" :icone="Wallet" />
                </div>

                <FaturamentoProjecao :faturamento="m.faturamento" :projecao="m.projecao" :periodo="m.periodo.rotulo" class="mt-4" />

                <section class="mt-4">
                    <SectionHeader titulo="Por vendedor" descricao="Novos, ganhos e perdidos no período; ativos e previsão são o retrato de hoje." class="mb-3" />
                    <DataTable id="relatorio-vendedores" :colunas="colunas" :linhas="linhas" chave="user_id" :meta="null" :ordenacao="ordem" :linhas-clicaveis="false" :cabecalho-fixo="false" :vazio="{ titulo: 'Ninguém na equipe ainda' }" @update:ordenacao="ordem = $event">
                        <template #celula-nome="{ linha }">
                            <Link :href="route('leads.index', { user_id: linha.user_id })" class="font-medium text-fg-0 hover:text-primary">{{ linha.nome }}</Link>
                            <span v-if="!linha.ativo" class="ml-2 text-xs text-fg-2">(inativo)</span>
                        </template>
                    </DataTable>
                </section>

                <AppCard class="mt-4">
                    <SectionHeader titulo="Ganhos por mês" :descricao="`Últimos 12 meses · ${formatBRL(totalAno)}`" />
                    <StatBar :itens="meses" orientacao="vertical" :formatar="(v) => (v ? formatBRL(v).replace(',00', '') : '')" class="mt-4" />
                </AppCard>
            </div>
        </PageContainer>
    </AuthenticatedLayout>
</template>
