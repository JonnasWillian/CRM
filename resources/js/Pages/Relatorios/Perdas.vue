<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleDollarSign, CircleX, Tag } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterChips from '@/Components/ui/FilterChips.vue';
import KpiCard from '@/Components/ui/KpiCard.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PeriodoSelector from '@/Components/ui/PeriodoSelector.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import StatBar from '@/Components/ui/StatBar.vue';
import AbasDeRelatorio from '@/Components/relatorios/AbasDeRelatorio.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { formatBRL, formatarData } from '@/utils/formatadores';

/** "Por que perdemos": perdas por motivo no período, com o valor que estava em jogo. Filtros na URL. */
const props = defineProps({
    relatorio: { type: Object, required: true }, // { periodo, tipo, total, valor_total, por_motivo[], por_tipo }
    periodo: { type: Object, required: true }, // { periodo, de, ate, tipo }
});

const filtros = ref({ ...props.periodo });
const carregando = ref(false);
function aplicar(parcial) {
    filtros.value = { ...filtros.value, ...parcial };
    const f = filtros.value;
    if (f.periodo === 'custom' && (!f.de || !f.ate)) return;
    const query = {};
    if (f.periodo) query.periodo = f.periodo;
    if (f.periodo === 'custom') { query.de = f.de; query.ate = f.ate; }
    if (f.tipo) query.tipo = f.tipo;
    router.get(route('relatorios.perdas'), query, {
        only: ['relatorio', 'periodo'], preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { carregando.value = true; },
        onFinish: () => { carregando.value = false; detalhe.recarregar(); },
    });
}
const periodoModel = computed({ get: () => ({ periodo: filtros.value.periodo === 'custom' || ['7d', '30d', 'mes_atual', 'mes_anterior'].includes(filtros.value.periodo) ? filtros.value.periodo : 'custom', de: filtros.value.de ?? props.relatorio.periodo.de, ate: filtros.value.ate ?? props.relatorio.periodo.ate }), set: (v) => aplicar(v) });
const TIPOS = [{ valor: null, rotulo: 'Leads e projetos' }, { valor: 'lead', rotulo: 'Leads' }, { valor: 'projeto', rotulo: 'Projetos' }];

const r = computed(() => props.relatorio);
const principal = computed(() => r.value.por_motivo[0] ?? null);
// Detalhe: as perdas uma a uma, com os mesmos filtros do resumo.
const detalhe = useListagemAxios(() => route('api.relatorios.perdas.detalhe'), { params: () => ({ ...filtrosDaUrl() }), padrao: { per_page: 25 } });
function filtrosDaUrl() {
    const f = filtros.value;
    return { periodo: f.periodo, ...(f.periodo === 'custom' ? { de: f.de, ate: f.ate } : {}), ...(f.tipo ? { tipo: f.tipo } : {}) };
}
const colunasDetalhe = [
    { key: 'nome', rotulo: 'Lead / projeto', primaria: true },
    { key: 'motivo', rotulo: 'Motivo' },
    { key: 'valor', rotulo: 'Valor', alinhar: 'direita', ocultarAbaixo: 'sm', formatar: (v) => (v === null ? '—' : formatBRL(v)) },
    { key: 'vendedor', rotulo: 'Registrado por', ocultarAbaixo: 'md', formatar: (v) => v ?? '—' },
    { key: 'data', rotulo: 'Data', ocultarAbaixo: 'sm', formatar: (v) => formatarData(v) },
];

const barras = computed(() => r.value.por_motivo.map((m) => ({ rotulo: m.arquivado ? `${m.descricao} (arquivado)` : m.descricao, valor: m.total })));
</script>

<template>
    <Head title="Por que perdemos" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Por que perdemos" :subtitulo="`${r.periodo.rotulo} · ${r.tipo === 'lead' ? 'só leads' : r.tipo === 'projeto' ? 'só projetos' : 'leads e projetos'}`">
                <template #abaixo>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <PeriodoSelector v-model="periodoModel" />
                        <FilterChips :model-value="filtros.tipo ?? null" :opcoes="TIPOS" @update:model-value="aplicar({ tipo: $event })" />
                    </div>
                </template>
            </PageHeader>

            <AbasDeRelatorio atual="relatorios.perdas" />
            <div :class="carregando ? 'opacity-60' : ''" :aria-busy="carregando ? 'true' : undefined">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <KpiCard rotulo="Perdas no período" :valor="r.total" :sub="`${r.por_tipo.lead} lead${r.por_tipo.lead === 1 ? '' : 's'} · ${r.por_tipo.projeto} projeto${r.por_tipo.projeto === 1 ? '' : 's'}`" :icone="CircleX" tom="danger" contar />
                    <KpiCard rotulo="Valor perdido" :valor="formatBRL(r.valor_total)" sub="Σ do que estava em jogo" :icone="CircleDollarSign" />
                    <KpiCard rotulo="Motivo principal" :valor="principal?.descricao ?? '—'" :sub="principal ? `${principal.percentual.toLocaleString('pt-BR')}% das perdas` : null" :icone="Tag" />
                </div>

                <AppCard class="mt-4">
                    <SectionHeader titulo="Perdas por motivo" descricao="Percentual sobre a quantidade; o valor vai ao lado." />
                    <EmptyState v-if="r.total === 0" :icone="CircleX" titulo="Nenhuma perda no período" descricao="Isso é bom — ou ninguém registrou. Amplie o período para comparar." tamanho="sm" />
                    <template v-else>
                        <StatBar :itens="barras" class="mt-4" />
                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full text-md">
                                <thead class="text-xs uppercase tracking-wider text-fg-2">
                                    <tr><th class="py-2 text-left font-medium">Motivo</th><th class="py-2 text-right font-medium">Perdas</th><th class="py-2 text-right font-medium">%</th><th class="py-2 text-right font-medium">Valor</th></tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr v-for="m in r.por_motivo" :key="m.motivo_perda_id">
                                        <td class="py-2 text-fg-0">{{ m.descricao }} <AppBadge v-if="m.arquivado" tom="warn" class="ml-1">Arquivado</AppBadge></td>
                                        <td class="num py-2 text-right">{{ m.total }}</td>
                                        <td class="num py-2 text-right text-fg-2">{{ m.percentual.toLocaleString('pt-BR') }}%</td>
                                        <td class="num py-2 text-right">{{ formatBRL(m.valor) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </AppCard>

                <section v-if="r.total > 0" class="mt-4">
                    <SectionHeader titulo="Perdas uma a uma" descricao="Mais recentes primeiro." class="mb-3" />
                    <DataTable id="perdas-detalhe" :colunas="colunasDetalhe" :linhas="detalhe.linhas.value" :meta="detalhe.meta.value" :carregando="detalhe.carregando.value" :erro="detalhe.erro.value" :linhas-clicaveis="false" :cabecalho-fixo="false" @update:pagina="detalhe.irParaPagina" @update:por-pagina="detalhe.porPagina" @tentar-novamente="detalhe.recarregar">
                        <template #celula-nome="{ linha }">
                            <Link v-if="linha.lead_id" :href="route('leads.show', linha.lead_id)" class="font-medium text-fg-0 hover:text-primary">{{ linha.nome ?? '—' }}</Link>
                            <span v-else>{{ linha.nome ?? '—' }}</span>
                            <AppBadge class="ml-2">{{ linha.tipo === 'lead' ? 'Lead' : 'Projeto' }}</AppBadge>
                            <p v-if="linha.observacao" class="truncate text-sm text-fg-2">{{ linha.observacao }}</p>
                        </template>
                    </DataTable>
                </section>
            </div>
        </PageContainer>
    </AuthenticatedLayout>
</template>
