<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CircleDollarSign, Telescope } from 'lucide-vue-next';
import { formatBRL } from '@/utils/formatadores';

/**
 * O que já está entrando × o que ainda é projeção, lado a lado e com a
 * origem de cada número. Faturando: recorrência (mês atual e no período) +
 * projetos de valor único ganhos no período. Projeção: o que falta dos
 * contratos recorrentes, projetos únicos em negociação e leads em
 * negociação (valor e ponderado pela probabilidade).
 */
const props = defineProps({
    faturamento: { type: Object, required: true },
    projecao: { type: Object, required: true },
    periodo: { type: String, default: 'no período' }, // rótulo do período ("Últimos 30 dias")
});

const n = (v, s, p) => `${v} ${v === 1 ? s : p}`;
const linhasFaturando = computed(() => [
    { rotulo: 'Recorrência deste mês (MRR)', valor: props.faturamento.mrr, sub: n(props.faturamento.contratos_vigentes, 'contrato vigente', 'contratos vigentes'), destaque: true },
    { rotulo: 'Recorrência faturada no período', valor: props.faturamento.recorrente_no_periodo, sub: 'meses de contrato iniciados no período' },
    { rotulo: 'Projetos de valor único ganhos', valor: props.faturamento.projetos_unicos_no_periodo, sub: 'fechados no período' },
]);
const linhasProjecao = computed(() => [
    { rotulo: 'Recorrência a realizar', valor: props.projecao.recorrente_a_realizar, sub: [props.projecao.contratos_a_iniciar ? n(props.projecao.contratos_a_iniciar, 'contrato a iniciar', 'contratos a iniciar') : null, props.projecao.contratos_vencendo_30d ? n(props.projecao.contratos_vencendo_30d, 'vence em 30 dias', 'vencem em 30 dias') : null].filter(Boolean).join(' · ') || 'meses restantes dos contratos', alerta: props.projecao.contratos_vencendo_30d > 0 },
    { rotulo: 'Projetos em negociação', valor: props.projecao.projetos_em_negociacao, sub: n(props.projecao.projetos_em_negociacao_qtd, 'projeto em aberto', 'projetos em aberto') },
    { rotulo: 'Leads em negociação (ponderado)', valor: props.projecao.leads_ponderado, sub: `${formatBRL(props.projecao.leads_em_negociacao)} sem ponderar`, href: route('leads.index', { situacao: 'ativos' }) },
]);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-lg border border-ok-line bg-bg-1 p-4 shadow-1 md:p-5" aria-labelledby="titulo-faturando">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="titulo-faturando" class="flex items-center gap-2 font-display text-lg font-semibold text-fg-0"><CircleDollarSign :size="18" class="text-ok" aria-hidden="true" />Faturando</h2>
                    <p class="text-sm text-fg-2">O que já está entrando · {{ periodo }}</p>
                </div>
                <div class="text-right">
                    <p class="num font-display text-2xl font-semibold text-ok">{{ formatBRL(faturamento.total_no_periodo) }}</p>
                    <p class="text-xs text-fg-2">total no período</p>
                </div>
            </div>
            <dl class="mt-4 divide-y divide-line">
                <div v-for="l in linhasFaturando" :key="l.rotulo" class="flex items-baseline justify-between gap-3 py-2.5">
                    <dt class="min-w-0"><span class="block text-md text-fg-0" :class="l.destaque ? 'font-semibold' : ''">{{ l.rotulo }}</span><span class="block text-xs text-fg-2">{{ l.sub }}</span></dt>
                    <dd class="num shrink-0 font-medium text-fg-0" :class="l.destaque ? 'text-lg' : ''">{{ formatBRL(l.valor) }}</dd>
                </div>
            </dl>
            <p v-if="faturamento.recorrente_acumulado" class="mt-2 text-xs text-fg-2">Contratos recorrentes já contabilizaram {{ formatBRL(faturamento.recorrente_acumulado) }} desde o início.</p>
        </section>

        <section class="rounded-lg border border-info-line bg-bg-1 p-4 shadow-1 md:p-5" aria-labelledby="titulo-projecao">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="titulo-projecao" class="flex items-center gap-2 font-display text-lg font-semibold text-fg-0"><Telescope :size="18" class="text-info" aria-hidden="true" />Em projeção</h2>
                    <p class="text-sm text-fg-2">O que ainda pode entrar</p>
                </div>
                <div class="text-right">
                    <p class="num font-display text-2xl font-semibold text-info">{{ formatBRL(projecao.total) }}</p>
                    <p class="text-xs text-fg-2">total previsto (leads pelo ponderado)</p>
                </div>
            </div>
            <dl class="mt-4 divide-y divide-line">
                <div v-for="l in linhasProjecao" :key="l.rotulo" class="flex items-baseline justify-between gap-3 py-2.5">
                    <dt class="min-w-0">
                        <component :is="l.href ? Link : 'span'" :href="l.href" class="block text-md text-fg-0" :class="l.href ? 'hover:text-primary' : ''">{{ l.rotulo }}</component>
                        <span class="block text-xs" :class="l.alerta ? 'font-medium text-warn' : 'text-fg-2'">{{ l.sub }}</span>
                    </dt>
                    <dd class="num shrink-0 font-medium text-fg-0">{{ formatBRL(l.valor) }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>
