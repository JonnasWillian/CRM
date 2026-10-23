<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, CreditCard } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatarData } from '@/utils/formatadores';

/** Situação da assinatura, uso contra os limites e a escolha do plano (mensal ou anual). */
const props = defineProps({
    assinatura: { type: Object, required: true }, // { status, plano, plano_nome, ciclo, dias_restantes, teste_termina_em, vigente_ate, bloqueada, limites, uso }
    planos: { type: Array, required: true },
    diasDeTeste: { type: Number, required: true },
});

const { confirmar } = useConfirm();
const ciclo = ref(props.assinatura.ciclo ?? 'anual');
const a = computed(() => props.assinatura);

const SITUACAO = {
    teste: ['Em teste', 'info'],
    teste_expirado: ['Teste encerrado', 'danger'],
    ativa: ['Ativa', 'ok'],
    vencida: ['Vencida', 'danger'],
    cancelada: ['Cancelada', 'danger'],
};
const descricaoDaSituacao = computed(() => ({
    teste: `Todos os recursos liberados por ${props.diasDeTeste} dias. Termina em ${formatarData(a.value.teste_termina_em)} (${a.value.dias_restantes} dia${a.value.dias_restantes === 1 ? '' : 's'}).`,
    teste_expirado: 'O período de teste terminou. A leitura continua liberada; para voltar a editar, escolha um plano abaixo.',
    ativa: `Plano ${a.value.plano_nome}, ciclo ${a.value.ciclo}, vigente até ${formatarData(a.value.vigente_ate)}.`,
    vencida: `O plano ${a.value.plano_nome} venceu em ${formatarData(a.value.vigente_ate)}. Renove para voltar a editar.`,
    cancelada: 'A assinatura foi cancelada. Escolha um plano para reativar.',
}[a.value.status]));

const RECURSOS = [{ chave: 'usuarios', rotulo: 'Usuários ativos' }, { chave: 'funis', rotulo: 'Funis' }, { chave: 'leads', rotulo: 'Leads' }];
const barra = (chave) => {
    const limite = a.value.limites[chave];
    const uso = a.value.uso[chave];
    return { uso, limite, pct: limite ? Math.min(100, Math.round((uso / limite) * 100)) : null, cheio: limite !== null && uso >= limite };
};

const form = useForm({ plano: null, ciclo: 'anual' });
async function escolher(plano) {
    const atual = a.value.status === 'ativa' && a.value.plano === plano.chave && a.value.ciclo === ciclo.value;
    const ok = await confirmar({
        titulo: atual ? `Renovar o plano ${plano.nome}?` : `Ativar o plano ${plano.nome} (${ciclo.value})?`,
        mensagem: atual
            ? `Mais ${ciclo.value === 'anual' ? '12 meses' : '1 mês'} somados ao vencimento atual.`
            : `R$ ${ciclo.value === 'anual' ? plano.preco_anual : plano.preco_mensal} por usuário ativo por mês${ciclo.value === 'anual' ? ', cobrado por ano' : ''}. Você pode trocar de plano depois.`,
        rotuloConfirmar: atual ? 'Renovar' : 'Ativar plano',
        tom: 'primary',
    });
    if (!ok) return;
    form.plano = plano.chave;
    form.ciclo = ciclo.value;
    form.post(route('configuracoes.assinatura.escolher'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Assinatura" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Assinatura" subtitulo="Seu plano, o uso da empresa e as opções para crescer." />

            <AppCard>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-lg font-semibold text-fg-0">Plano {{ a.plano_nome }}</h2>
                            <AppBadge :tom="SITUACAO[a.status]?.[1] ?? 'neutral'" ponto>{{ SITUACAO[a.status]?.[0] ?? a.status }}</AppBadge>
                        </div>
                        <p class="mt-1 text-md text-fg-1">{{ descricaoDaSituacao }}</p>
                    </div>
                    <CreditCard :size="22" class="text-fg-2" aria-hidden="true" />
                </div>
                <AppAlert v-if="a.bloqueada" tom="danger" class="mt-4">Enquanto não houver plano ativo, a equipe só consulta: nada é criado nem alterado.</AppAlert>

                <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div v-for="r in RECURSOS" :key="r.chave">
                        <dt class="flex items-center justify-between text-sm text-fg-2"><span>{{ r.rotulo }}</span><span class="num" :class="barra(r.chave).cheio ? 'font-medium text-warn' : ''">{{ barra(r.chave).uso }}{{ barra(r.chave).limite !== null ? ` de ${barra(r.chave).limite}` : '' }}</span></dt>
                        <dd class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-bg-2">
                            <div class="h-full rounded-full" :class="barra(r.chave).cheio ? 'bg-warn-accent' : 'bg-primary'" :style="{ width: `${barra(r.chave).pct ?? 8}%` }" />
                        </dd>
                        <p v-if="barra(r.chave).limite === null" class="mt-1 text-xs text-fg-2">{{ a.status === 'teste' ? 'Sem limite durante o teste' : 'Sem limite neste plano' }}</p>
                    </div>
                </dl>
            </AppCard>

            <section class="mt-6">
                <SectionHeader titulo="Planos" descricao="Por usuário ativo, em reais. Usuários desativados não contam.">
                    <template #acoes>
                        <div role="radiogroup" aria-label="Ciclo" class="inline-flex rounded border border-line bg-bg-1 p-0.5">
                            <button v-for="c in [{ v: 'mensal', r: 'Mensal' }, { v: 'anual', r: 'Anual · ~20% off' }]" :key="c.v" type="button" role="radio" :aria-checked="ciclo === c.v ? 'true' : 'false'" class="h-8 rounded-sm px-3 text-sm font-medium transition-colors duration-1 ease-ui" :class="ciclo === c.v ? 'bg-primary-subtle text-primary' : 'text-fg-1 hover:text-fg-0'" @click="ciclo = c.v">{{ c.r }}</button>
                        </div>
                    </template>
                </SectionHeader>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <article v-for="p in planos" :key="p.chave" class="relative flex flex-col rounded-xl border bg-bg-1 p-5 shadow-1" :class="a.plano === p.chave && a.status === 'ativa' ? 'border-ok' : p.destaque ? 'border-primary' : 'border-line'">
                        <AppBadge v-if="a.plano === p.chave && a.status === 'ativa'" tom="ok" class="absolute -top-2.5 left-5">Plano atual</AppBadge>
                        <AppBadge v-else-if="p.destaque" tom="primary" class="absolute -top-2.5 left-5">Mais escolhido</AppBadge>
                        <h3 class="font-display text-lg font-semibold">{{ p.nome }}</h3>
                        <p class="mt-1 text-sm text-fg-2">{{ p.descricao }}</p>
                        <p class="num mt-4"><span class="font-display text-3xl font-bold">R$ {{ ciclo === 'anual' ? p.preco_anual : p.preco_mensal }}</span><span class="text-sm text-fg-2"> /usuário/mês</span></p>
                        <p class="text-xs text-fg-2">{{ ciclo === 'anual' ? `R$ ${p.preco_anual * 12} por usuário por ano` : 'cobrado todo mês' }}</p>
                        <p class="mt-3 text-sm font-medium text-fg-1">{{ p.limite }}</p>
                        <ul class="mt-3 flex-1 space-y-1.5 text-sm text-fg-0">
                            <li v-for="item in p.inclui" :key="item" class="flex items-start gap-2"><Check :size="14" class="mt-0.5 shrink-0 text-ok" aria-hidden="true" />{{ item }}</li>
                        </ul>
                        <AppButton class="mt-5" :variante="p.destaque || (a.plano === p.chave && a.status === 'ativa') ? 'primary' : 'secondary'" :carregando="form.processing && form.plano === p.chave" @click="escolher(p)">
                            {{ a.status === 'ativa' && a.plano === p.chave ? 'Renovar' : a.status === 'ativa' ? 'Trocar para este' : 'Ativar plano' }}
                        </AppButton>
                    </article>
                </div>
                <p class="mt-4 text-sm text-fg-2">Dúvidas ou equipe grande? Fale com a gente pelo e-mail de suporte.</p>
            </section>
        </PageContainer>
    </AuthenticatedLayout>
</template>
