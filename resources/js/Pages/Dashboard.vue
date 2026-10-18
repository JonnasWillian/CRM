<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, CalendarClock, CircleCheck, CircleDollarSign, CircleX, Plus, SquareCheck, TrendingUp, UserPlus, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppSkeleton from '@/Components/ui/AppSkeleton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import KpiCard from '@/Components/ui/KpiCard.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PeriodoSelector from '@/Components/ui/PeriodoSelector.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import SkeletonKpi from '@/Components/ui/SkeletonKpi.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import StatBar from '@/Components/ui/StatBar.vue';
import { useToast } from '@/Composables/useToast';
import { diasDesde, formatBRL, formatarData, parseDataLocal } from '@/utils/formatadores';

/**
 * Dashboard: período/vendedor/funil na URL; `metricas` e `tarefas` chegam
 * como props diferidas (esqueleto até lá). Nada de grade aqui — a lista é
 * a página Leads; o quadro, o Pipeline.
 */
const props = defineProps({
    periodo: { type: Object, required: true }, // { periodo, de, ate, user_id, funil_id }
    presets: { type: Array, required: true },
    funis: { type: Array, required: true },
    agentes: { type: Array, default: () => [] },
    metricas: { type: Object, default: null },
    tarefas: { type: Object, default: null },
});

const toast = useToast();
const eu = computed(() => usePage().props.auth.user);
const pode = (p) => usePage().props.auth.permissions?.[p] === true;

const saudacao = computed(() => {
    const h = new Date().getHours();
    const momento = h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite';
    return `${momento}, ${String(eu.value?.name ?? '').split(' ')[0]}`;
});
const hoje = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });

// ── Filtros (URL) ──
const filtros = ref({ ...props.periodo });
const carregando = ref(false);
function aplicar(parcial) {
    filtros.value = { ...filtros.value, ...parcial };
    const f = filtros.value;
    if (f.periodo === 'custom' && (!f.de || !f.ate)) return; // espera as duas datas
    const query = {};
    if (f.periodo && f.periodo !== '30d') query.periodo = f.periodo;
    if (f.periodo === 'custom') { query.de = f.de; query.ate = f.ate; }
    if (f.user_id) query.user_id = f.user_id;
    if (f.funil_id) query.funil_id = f.funil_id;
    router.get(route('dashboard'), query, {
        only: ['metricas', 'tarefas', 'periodo'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { carregando.value = true; },
        onFinish: () => { carregando.value = false; },
    });
}
const periodoModel = computed({ get: () => ({ periodo: filtros.value.periodo, de: filtros.value.de, ate: filtros.value.ate }), set: (v) => aplicar(v) });
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.name })));
const opcoesFunil = computed(() => props.funis.map((f) => ({ valor: f.id, rotulo: f.nome })));

// ── Indicadores ──
const m = computed(() => props.metricas);
const periodoQuery = computed(() => (m.value ? { de: m.value.periodo.inicio, ate: m.value.periodo.fim } : {}));
const taxaGanho = computed(() => (m.value?.fluxo.taxa_ganho === null || m.value?.fluxo.taxa_ganho === undefined ? '—' : `${m.value.fluxo.taxa_ganho.toLocaleString('pt-BR')}%`));
const porEstagio = computed(() => (m.value?.estado.leads_por_estagio ?? []).map((e) => ({
    rotulo: e.descricao,
    valor: e.total,
    cor: e.tipo === 'ganho' ? 'var(--ok)' : e.tipo === 'perdido' ? 'var(--danger)' : e.cor || 'var(--primary)',
    href: e.id ? route('leads.index', { 'estagios[]': e.id, ...(filtros.value.user_id ? { user_id: filtros.value.user_id } : {}) }) : null,
})));
const forecastSub = computed(() => {
    const f = m.value?.forecast;
    if (!f) return null;
    const partes = [`${f.leads_com_previsao} lead${f.leads_com_previsao === 1 ? '' : 's'} com previsão`, `${formatBRL(f.valor_estimado_aberto)} sem ponderar`];
    if (f.sem_probabilidade) partes.push(`faltam ${f.sem_probabilidade} probabilidade${f.sem_probabilidade === 1 ? '' : 's'}`);
    return partes.join(' · ');
});
const nomeDoMes = (ym) => parseDataLocal(`${ym}-01`)?.toLocaleDateString('pt-BR', { month: 'short' }) ?? ym;

// ── Tarefas ──
const t = computed(() => props.tarefas);
const recarregarTarefas = () => router.reload({ only: ['tarefas'] });
const vencimento = (tarefa) => {
    const dias = diasDesde(parseDataLocal(tarefa.data_limite));
    if (dias > 0) return `há ${dias} dia${dias === 1 ? '' : 's'}`;
    if (dias === 0) return 'hoje';
    return formatarData(tarefa.data_limite);
};
async function concluir(tarefa) {
    try {
        await axios.put(route('api.tarefas.update', tarefa.id), { concluido: true });
        toast.sucesso('Tarefa concluída.', { acao: { rotulo: 'Desfazer', onClick: () => axios.put(route('api.tarefas.update', tarefa.id), { concluido: false }).then(recarregarTarefas) } });
        recarregarTarefas();
    } catch { /* interceptor */ }
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader :titulo="saudacao" :subtitulo="hoje">
                <template #acoes>
                    <AppButton v-if="pode('leads.manage')" as="Link" :href="route('leads.index', { novo: 1 })"><template #icone><Plus /></template>Novo lead</AppButton>
                </template>
                <template #abaixo>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <PeriodoSelector v-model="periodoModel" />
                        <AppSelect v-if="agentes.length" :model-value="filtros.user_id" :opcoes="opcoesAgente" placeholder="Toda a equipe" tamanho="sm" aria-label="Vendedor" class="w-44" @update:model-value="aplicar({ user_id: $event })" />
                        <AppSelect v-if="funis.length > 1" :model-value="filtros.funil_id" :opcoes="opcoesFunil" placeholder="Todos os funis" tamanho="sm" aria-label="Funil" class="w-44" @update:model-value="aplicar({ funil_id: $event })" />
                    </div>
                </template>
            </PageHeader>

            <Deferred :data="['metricas']">
                <template #fallback>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6"><SkeletonKpi v-for="i in 6" :key="i" /></div>
                </template>

                <!-- Empresa sem leads -->
                <AppCard v-if="m && m.estado.total_leads === 0 && !filtros.user_id && !filtros.funil_id">
                    <EmptyState :icone="Users" titulo="Sua empresa ainda não tem leads" descricao="Cadastre o primeiro ou traga sua planilha.">
                        <template #acao>
                            <AppButton v-if="pode('leads.manage')" as="Link" :href="route('leads.index', { novo: 1 })"><template #icone><Plus /></template>Novo lead</AppButton>
                        </template>
                    </EmptyState>
                </AppCard>

                <template v-else-if="m">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" :class="carregando ? 'opacity-60' : ''" :aria-busy="carregando ? 'true' : undefined">
                        <KpiCard rotulo="Leads ativos" :valor="m.estado.leads_ativos" :sub="`+ ${m.estado.leads_fechados} ganho${m.estado.leads_fechados === 1 ? '' : 's'}/perdido${m.estado.leads_fechados === 1 ? '' : 's'}`" :icone="Users" tom="primary" contar :href="route('leads.index', { situacao: 'ativos' })" />
                        <KpiCard rotulo="Novos" :valor="m.fluxo.leads_criados" :sub="m.periodo.rotulo" :icone="UserPlus" contar :href="route('leads.index', periodoQuery)" />
                        <KpiCard rotulo="Ganhos" :valor="m.fluxo.ganhos.total" :sub="`${formatBRL(m.fluxo.ganhos.valor)} no período`" :icone="CircleCheck" tom="ok" contar :href="route('leads.index', { situacao: 'ganhos', ...periodoQuery })" />
                        <KpiCard rotulo="Perdidos" :valor="m.fluxo.perdidos.total" :sub="`${formatBRL(m.fluxo.perdidos.valor)} no período`" :icone="CircleX" tom="danger" contar :href="pode('leads.view-all') ? route('relatorios.perdas') : null" />
                        <KpiCard rotulo="Taxa de ganho" :valor="taxaGanho" :sub="m.fluxo.ticket_medio ? `Ticket médio ${formatBRL(m.fluxo.ticket_medio)}` : 'ganhos ÷ (ganhos + perdidos)'" :icone="TrendingUp" />
                        <KpiCard rotulo="Sem contato +14d" :valor="m.estado.sem_contato_14d" sub="leads abertos parados" :icone="AlertTriangle" :tom="m.estado.sem_contato_14d ? 'warn' : 'neutral'" contar :href="route('leads.index', { estagnados: 1 })" />
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <AppCard>
                            <SectionHeader titulo="Leads por estágio" :descricao="filtros.funil_id ? null : 'Todos os funis'" />
                            <StatBar v-if="porEstagio.length" :itens="porEstagio" class="mt-4" />
                            <p v-else class="mt-4 text-sm text-fg-2">Nenhum lead nos filtros atuais.</p>
                        </AppCard>
                        <AppCard>
                            <SectionHeader titulo="Previsão de fechamento" descricao="Leads abertos, ponderados pela probabilidade" />
                            <Link :href="route('leads.index', { situacao: 'ativos', previsao_de: new Date().toISOString().slice(0, 10) })" class="mt-4 block rounded-md hover:bg-bg-2/60">
                                <p class="num font-display text-3xl font-semibold leading-none text-fg-0">{{ formatBRL(m.forecast.valor_ponderado) }}</p>
                                <p class="mt-2 text-sm text-fg-2">{{ forecastSub }}</p>
                                <p v-if="m.forecast.valor_projetos_abertos" class="mt-1 text-sm text-fg-2">{{ formatBRL(m.forecast.valor_projetos_abertos) }} em projetos abertos</p>
                                <p v-if="m.forecast.previsao_vencida" class="mt-1 text-sm font-medium text-danger">{{ m.forecast.previsao_vencida }} com previsão vencida</p>
                            </Link>
                            <StatBar v-if="m.forecast.por_mes?.some((x) => x.total)" :itens="m.forecast.por_mes.map((x) => ({ rotulo: nomeDoMes(x.mes), valor: x.valor_ponderado }))" :formatar="(v) => formatBRL(v)" class="mt-4" />
                        </AppCard>
                    </div>
                </template>
            </Deferred>

            <!-- Tarefas -->
            <AppCard class="mt-4">
                <SectionHeader titulo="Tarefas" :descricao="t ? `${t.proximos_7_dias.total} nos próximos 7 dias` : null">
                    <template #acoes>
                        <AppButton variante="ghost" tamanho="sm" as="Link" :href="route('tarefas.index', { escopo: 'pendentes' })">Ver todas</AppButton>
                    </template>
                </SectionHeader>
                <Deferred :data="['tarefas']">
                    <template #fallback><SkeletonRows :linhas="4" :colunas="3" class="mt-3" /></template>
                    <div v-if="t" class="mt-3 grid gap-4 md:grid-cols-2">
                        <section v-for="grupo in [{ chave: 'atrasadas', titulo: 'Atrasadas', tom: 'danger' }, { chave: 'hoje', titulo: 'Hoje', tom: 'warn' }]" :key="grupo.chave">
                            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-fg-0">
                                {{ grupo.titulo }}
                                <AppBadge :tom="t[grupo.chave].total ? grupo.tom : 'neutral'">{{ t[grupo.chave].total }}</AppBadge>
                            </h3>
                            <ul v-if="t[grupo.chave].itens.length" class="divide-y divide-line rounded-md border border-line" role="list">
                                <li v-for="tarefa in t[grupo.chave].itens" :key="tarefa.id" class="flex items-center gap-3 px-3 py-2">
                                    <input type="checkbox" :aria-label="`Concluir ${tarefa.titulo}`" class="form-checkbox h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-focus/25" @change="concluir(tarefa)" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-md text-fg-0">{{ tarefa.titulo }}</p>
                                        <p class="truncate text-sm text-fg-2">
                                            <Link v-if="tarefa.lead" :href="route('leads.show', { usuario: tarefa.lead.id, aba: 'tarefas' })" class="hover:text-primary">{{ tarefa.lead.nome }}</Link>
                                            <span v-if="grupo.chave === 'atrasadas'" class="text-danger"> · venceu {{ vencimento(tarefa) }}</span>
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="rounded-md border border-dashed border-line px-3 py-4 text-center text-sm text-fg-2">{{ grupo.chave === 'hoje' ? 'Nada vence hoje.' : 'Nenhuma tarefa atrasada.' }}</p>
                            <Link v-if="t[grupo.chave].total > t[grupo.chave].itens.length" :href="route('tarefas.index', { escopo: grupo.chave })" class="mt-2 inline-block text-sm font-medium text-primary hover:underline">Ver todas ({{ t[grupo.chave].total }})</Link>
                        </section>
                    </div>
                </Deferred>
            </AppCard>
        </PageContainer>
    </AuthenticatedLayout>
</template>
