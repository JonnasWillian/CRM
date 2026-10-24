<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import axios from "axios";
import { Check, CreditCard } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppAlert from "@/Components/ui/AppAlert.vue";
import AppBadge from "@/Components/ui/AppBadge.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import AppCard from "@/Components/ui/AppCard.vue";
import PageContainer from "@/Components/ui/PageContainer.vue";
import PageHeader from "@/Components/ui/PageHeader.vue";
import SectionHeader from "@/Components/ui/SectionHeader.vue";
import { useConfirm } from "@/Composables/useConfirm";
import { formatBRL, formatarData } from "@/utils/formatadores";

/**
 * Situação da assinatura, fatura pendente e como pagar, uso × limites, os
 * planos com o valor para o número de usuários escolhido e o histórico de
 * faturas. Usuários são assentos: o plano pago cobre N. Contratar pede a
 * prévia ao servidor (tipo, crédito, valor), confirma e gera a fatura; o
 * plano só ativa quando o pagamento é confirmado.
 */
const props = defineProps({
    assinatura: { type: Object, required: true },
    planos: { type: Array, required: true }, // + excessos[]
    usuariosCobrados: { type: Number, required: true },
    ocupados: { type: Number, default: 1 }, // ativos + convites pendentes: mínimo de assentos
    faturaPendente: { type: Object, default: null },
    faturas: { type: Array, default: () => [] },
    pagamento: { type: Object, default: () => ({}) }, // { instrucoes, email, whatsapp }
    diasDeTeste: { type: Number, required: true },
});

const { confirmar } = useConfirm();
const ciclo = ref(props.assinatura.ciclo ?? "anual");
const a = computed(() => props.assinatura);
const emVigor = computed(() => ["ativa", "carencia"].includes(a.value.status));
const usuarios = (n) => `${n} ${n === 1 ? "usuário" : "usuários"}`;

// Assentos: começa no maior entre o que a empresa ocupa e o que já paga.
const minimoDeAssentos = computed(() => Math.max(1, props.ocupados));
const assentos = ref(Math.max(minimoDeAssentos.value, props.assinatura.assentos ?? 0));
const assentosValidos = computed(() => Math.max(minimoDeAssentos.value, Number.parseInt(assentos.value, 10) || 0));
const assentosDo = (p) => Math.max(p.usuarios_minimos ?? 1, assentosValidos.value);
const totalDo = (p) => (ciclo.value === "anual" ? p.preco_anual * 12 : p.preco_mensal) * assentosDo(p);
const acimaDoLimite = (p) => p.limites?.usuarios != null && assentosDo(p) > p.limites.usuarios;

const SITUACAO = {
    teste: ["Em teste", "info"],
    teste_expirado: ["Teste encerrado", "danger"],
    ativa: ["Ativa", "ok"],
    carencia: ["Pagamento pendente", "warn"],
    vencida: ["Vencida", "danger"],
    cancelada: ["Cancelada", "danger"],
};
const descricaoDaSituacao = computed(() => {
    const s = a.value;
    if (s.status === "ativa" && s.renovacao_cancelada)
        return `Plano ${s.plano_nome}, ciclo ${s.ciclo}. Ativo até ${formatarData(s.vigente_ate)} e não será renovado.`;
    return {
        teste: `Todos os recursos liberados por ${props.diasDeTeste} dias. Termina em ${formatarData(s.teste_termina_em)}.`,
        teste_expirado:
            "O período de teste terminou. A equipe só consulta até um plano ser pago.",
        ativa: `Plano ${s.plano_nome}, ciclo ${s.ciclo}, vigente até ${formatarData(s.vigente_ate)}.`,
        carencia: `O plano ${s.plano_nome} venceu em ${formatarData(s.vigente_ate)}. A equipe trabalha normalmente até ${formatarData(s.carencia_ate)}; depois disso, só consulta.`,
        vencida: `O plano ${s.plano_nome} venceu em ${formatarData(s.vigente_ate)}. A equipe só consulta até a renovação ser paga.`,
        cancelada:
            "A assinatura foi cancelada. A equipe só consulta até um plano ser pago.",
    }[s.status];
});

const RECURSOS = [
    { chave: "usuarios", rotulo: "Usuários ativos" },
    { chave: "funis", rotulo: "Funis" },
    { chave: "leads", rotulo: "Leads" },
];
const barra = (chave) => {
    const limite = a.value.limites[chave];
    // Convite pendente já ocupa vaga: a barra de usuários conta os dois.
    const uso =
        a.value.uso[chave] +
        (chave === "usuarios" ? (a.value.convites_pendentes ?? 0) : 0);
    return {
        uso,
        limite,
        pct: limite ? Math.min(100, Math.round((uso / limite) * 100)) : null,
        cheio: limite !== null && uso >= limite,
    };
};

const rotuloDoBotao = (p) => {
    if (p.excessos.length) return "Não cabe no uso atual";
    if (acimaDoLimite(p)) return `Até ${usuarios(p.limites.usuarios)}`;
    if (emVigor.value && a.value.plano === p.chave) {
        const mudou = ciclo.value !== (a.value.ciclo ?? "mensal") || assentosDo(p) !== (a.value.assentos ?? assentosDo(p));
        return mudou ? "Atualizar plano" : "Renovar agora";
    }
    if (emVigor.value) return "Trocar para este";
    return "Contratar";
};

const form = useForm({ plano: null, ciclo: "anual", assentos: null });
const erroDaEscolha = ref(null);
const consultando = ref(null);

async function contratar(plano) {
    erroDaEscolha.value = null;
    const escolha = { plano: plano.chave, ciclo: ciclo.value, assentos: assentosDo(plano) };
    let previa;
    consultando.value = plano.chave;
    try {
        ({ data: previa } = await axios.get(route("configuracoes.assinatura.previa"), { params: escolha }));
    } catch (e) {
        const dados = e?.response?.data;
        erroDaEscolha.value = Object.values(dados?.errors ?? {})[0]?.[0] ?? dados?.message ?? "Não foi possível consultar o valor. Tente novamente.";
        return;
    } finally {
        consultando.value = null;
    }

    const preco = ciclo.value === "anual" ? `${formatBRL(plano.preco_anual)} × 12 meses` : formatBRL(plano.preco_mensal);
    const conta = `${usuarios(previa.usuarios)} × ${preco} = ${formatBRL(previa.bruto)}`;
    const cancelaPendente = props.faturaPendente ? " A fatura pendente atual será cancelada." : "";
    const pedido = {
        troca: {
            titulo: `Trocar para o plano ${plano.nome}?`,
            mensagem: `${previa.credito > 0 ? `${conta}, menos ${formatBRL(previa.credito)} de crédito do que sobra do plano atual: ${formatBRL(previa.valor)}` : conta}.${cancelaPendente} O novo ciclo começa no dia em que o pagamento for confirmado.`,
        },
        renovacao: {
            titulo: `Gerar a fatura de renovação do plano ${plano.nome}?`,
            mensagem: `${conta}. Vence em ${formatarData(previa.vence_em)}.${cancelaPendente} O plano é renovado assim que o pagamento for confirmado.`,
        },
    }[previa.motivo] ?? {
        titulo: `Gerar fatura do plano ${plano.nome}?`,
        mensagem: `${conta}.${cancelaPendente} O plano é ativado assim que o pagamento for confirmado.`,
    };

    const ok = await confirmar({ ...pedido, rotuloConfirmar: "Gerar fatura", tom: "primary" });
    if (!ok) return;
    Object.assign(form, escolha);
    form.post(route("configuracoes.assinatura.escolher"), { preserveScroll: true });
}

const processando = ref(false);
async function cancelarRenovacao() {
    const ok = await confirmar({
        titulo: "Cancelar a renovação?",
        mensagem: `O plano ${a.value.plano_nome} continua ativo até ${formatarData(a.value.vigente_ate)}. Depois disso, a equipe só consulta os dados e, 30 dias depois, o acesso é suspenso. Você pode retomar até lá.`,
        rotuloConfirmar: "Cancelar renovação",
        tom: "danger",
    });
    if (!ok) return;
    router.post(
        route("configuracoes.assinatura.cancelar-renovacao"),
        {},
        {
            preserveScroll: true,
            onStart: () => (processando.value = true),
            onFinish: () => (processando.value = false),
        },
    );
}
const retomarRenovacao = () =>
    router.post(
        route("configuracoes.assinatura.retomar-renovacao"),
        {},
        {
            preserveScroll: true,
            onStart: () => (processando.value = true),
            onFinish: () => (processando.value = false),
        },
    );

const situacaoDaFatura = (f) => {
    if (f.status === "paga")
        return { rotulo: `Paga em ${formatarData(f.paga_em)}`, tom: "ok" };
    if (f.status === "cancelada")
        return { rotulo: "Cancelada", tom: "neutral" };
    return f.atrasada
        ? { rotulo: "Atrasada", tom: "danger" }
        : { rotulo: "Pendente", tom: "warn" };
};
const whatsapp = computed(() =>
    props.pagamento?.whatsapp
        ? `https://wa.me/${props.pagamento.whatsapp}`
        : null,
);
</script>

<template>
    <Head title="Assinatura" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader
                titulo="Assinatura"
                subtitulo="Seu plano, as faturas e o uso da empresa."
            />

            <AppCard>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2
                                class="font-display text-lg font-semibold text-fg-0"
                            >
                                Plano {{ a.plano_nome }}
                            </h2>
                            <AppBadge
                                :tom="SITUACAO[a.status]?.[1] ?? 'neutral'"
                                ponto
                                >{{
                                    SITUACAO[a.status]?.[0] ?? a.status
                                }}</AppBadge
                            >
                        </div>
                        <p class="mt-1 text-md text-fg-1">
                            {{ descricaoDaSituacao }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <AppButton
                            v-if="
                                a.status === 'ativa' && !a.renovacao_cancelada
                            "
                            variante="ghost"
                            tamanho="sm"
                            :carregando="processando"
                            @click="cancelarRenovacao"
                            >Cancelar renovação</AppButton
                        >
                        <AppButton
                            v-else-if="
                                a.status === 'ativa' && a.renovacao_cancelada
                            "
                            variante="secondary"
                            tamanho="sm"
                            :carregando="processando"
                            @click="retomarRenovacao"
                            >Retomar renovação</AppButton
                        >
                        <CreditCard
                            v-else
                            :size="22"
                            class="text-fg-2"
                            aria-hidden="true"
                        />
                    </div>
                </div>
                <AppAlert v-if="a.bloqueada" tom="danger" class="mt-4">
                    A equipe só consulta: nada é criado nem alterado.<template
                        v-if="a.suspende_em"
                    >
                        Em {{ formatarData(a.suspende_em) }}, o acesso será
                        suspenso.</template
                    >
                </AppAlert>

                <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div v-for="r in RECURSOS" :key="r.chave">
                        <dt
                            class="flex items-center justify-between text-sm text-fg-2"
                        >
                            <span>{{ r.rotulo }}</span>
                            <span
                                class="num"
                                :class="
                                    barra(r.chave).cheio
                                        ? 'font-medium text-warn'
                                        : ''
                                "
                                >{{ barra(r.chave).uso
                                }}{{
                                    barra(r.chave).limite !== null
                                        ? ` de ${barra(r.chave).limite}`
                                        : ""
                                }}</span
                            >
                        </dt>
                        <dd
                            class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-bg-2"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="
                                    barra(r.chave).cheio
                                        ? 'bg-warn-accent'
                                        : 'bg-primary'
                                "
                                :style="{
                                    width: `${barra(r.chave).pct ?? 8}%`,
                                }"
                            />
                        </dd>
                        <p
                            v-if="
                                r.chave === 'usuarios' && a.convites_pendentes
                            "
                            class="mt-1 text-xs text-fg-2"
                        >
                            Inclui {{ a.convites_pendentes }}
                            {{
                                a.convites_pendentes === 1
                                    ? "convite pendente"
                                    : "convites pendentes"
                            }}.
                        </p>
                        <p
                            v-else-if="barra(r.chave).limite === null"
                            class="mt-1 text-xs text-fg-2"
                        >
                            {{
                                a.status === "teste"
                                    ? "Sem limite durante o teste"
                                    : "Sem limite neste plano"
                            }}
                        </p>
                    </div>
                </dl>
            </AppCard>

            <AppCard v-if="faturaPendente" class="mt-4" data-fatura-pendente>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2
                                class="font-display text-lg font-semibold text-fg-0"
                            >
                                Fatura #{{ faturaPendente.id }}
                            </h2>
                            <AppBadge
                                :tom="
                                    faturaPendente.atrasada ? 'danger' : 'warn'
                                "
                                ponto
                                >{{
                                    faturaPendente.atrasada
                                        ? "Atrasada"
                                        : "Aguardando pagamento"
                                }}</AppBadge
                            >
                        </div>
                        <p class="mt-1 text-md text-fg-1">
                            Plano {{ faturaPendente.plano_nome }}, ciclo
                            {{ faturaPendente.ciclo }},
                            {{ usuarios(faturaPendente.usuarios) }}. Vence em
                            {{ formatarData(faturaPendente.vence_em) }}.
                        </p>
                        <p v-if="faturaPendente.credito > 0" class="mt-1 text-sm text-fg-2">Inclui {{ formatBRL(faturaPendente.credito) }} de crédito do plano atual.</p>
                    </div>
                    <p class="num font-display text-2xl font-bold text-fg-0">
                        {{ formatBRL(faturaPendente.valor) }}
                    </p>
                </div>
                <div
                    class="mt-4 rounded-md border border-line bg-bg-2 px-4 py-3 text-md"
                >
                    <p class="font-medium text-fg-0">Como pagar</p>
                    <p class="mt-1 whitespace-pre-line text-fg-1">
                        {{
                            pagamento.instrucoes ||
                            "Fale com a gente para receber os dados de pagamento."
                        }}
                    </p>
                    <p
                        v-if="pagamento.email || whatsapp"
                        class="mt-2 text-sm text-fg-2"
                    >
                        Envie o comprovante ou tire dúvidas:
                        <a
                            v-if="pagamento.email"
                            :href="`mailto:${pagamento.email}`"
                            class="font-medium text-primary hover:underline"
                            >{{ pagamento.email }}</a
                        >
                        <template v-if="pagamento.email && whatsapp">
                            ou
                        </template>
                        <a
                            v-if="whatsapp"
                            :href="whatsapp"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-primary hover:underline"
                            >WhatsApp</a
                        >
                    </p>
                </div>
                <p class="mt-3 text-sm text-fg-2">
                    O plano é ativado assim que o pagamento for confirmado. A
                    confirmação chega por e-mail.
                </p>
            </AppCard>

            <section class="mt-6">
                <SectionHeader
                    titulo="Planos"
                    :descricao="`Preço por usuário, em reais. Escolha quantos usuários o plano cobre: no mínimo ${usuarios(minimoDeAssentos)}, entre ativos e convites pendentes.`"
                >
                    <template #acoes>
                        <label class="flex items-center gap-2 text-sm text-fg-1">
                            Usuários
                            <input
                                v-model.number="assentos"
                                data-assentos
                                type="number"
                                inputmode="numeric"
                                step="1"
                                :min="minimoDeAssentos"
                                class="h-8 w-20 rounded border border-line bg-bg-1 px-2 text-md text-fg-0 focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/25"
                            />
                        </label>
                        <div
                            role="radiogroup"
                            aria-label="Ciclo"
                            class="inline-flex rounded border border-line bg-bg-1 p-0.5"
                        >
                            <button
                                v-for="c in [
                                    { v: 'mensal', r: 'Mensal' },
                                    { v: 'anual', r: 'Anual · ~20% off' },
                                ]"
                                :key="c.v"
                                type="button"
                                role="radio"
                                :aria-checked="ciclo === c.v ? 'true' : 'false'"
                                class="h-8 rounded-sm px-3 text-sm font-medium transition-colors duration-1 ease-ui"
                                :class="
                                    ciclo === c.v
                                        ? 'bg-primary-subtle text-primary'
                                        : 'text-fg-1 hover:text-fg-0'
                                "
                                @click="ciclo = c.v"
                            >
                                {{ c.r }}
                            </button>
                        </div>
                    </template>
                </SectionHeader>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="p in planos"
                        :key="p.chave"
                        :data-plano="p.chave"
                        class="relative flex flex-col rounded-xl border bg-bg-1 p-5 shadow-1"
                        :class="
                            a.plano === p.chave && emVigor
                                ? 'border-ok'
                                : p.destaque
                                  ? 'border-primary'
                                  : 'border-line'
                        "
                    >
                        <AppBadge
                            v-if="a.plano === p.chave && emVigor"
                            tom="ok"
                            class="absolute -top-2.5 left-5"
                            >Plano atual</AppBadge
                        >
                        <AppBadge
                            v-else-if="p.destaque"
                            tom="primary"
                            class="absolute -top-2.5 left-5"
                            >Mais escolhido</AppBadge
                        >
                        <h3 class="font-display text-lg font-semibold">
                            {{ p.nome }}
                        </h3>
                        <p class="mt-1 text-sm text-fg-2">{{ p.descricao }}</p>
                        <p class="num mt-4">
                            <span class="font-display text-3xl font-bold">{{
                                formatBRL(
                                    ciclo === "anual"
                                        ? p.preco_anual
                                        : p.preco_mensal,
                                )
                            }}</span
                            ><span class="text-sm text-fg-2">
                                /usuário/mês</span
                            >
                        </p>
                        <p class="num text-sm text-fg-1">
                            {{ usuarios(assentosDo(p)) }}:
                            {{ formatBRL(totalDo(p)) }}
                            {{ ciclo === "anual" ? "por ano" : "por mês" }}
                        </p>
                        <p class="mt-3 text-sm font-medium text-fg-1">
                            {{ p.limite }}
                        </p>
                        <ul class="mt-3 flex-1 space-y-1.5 text-sm text-fg-0">
                            <li
                                v-for="item in p.inclui"
                                :key="item"
                                class="flex items-start gap-2"
                            >
                                <Check
                                    :size="14"
                                    class="mt-0.5 shrink-0 text-ok"
                                    aria-hidden="true"
                                />{{ item }}
                            </li>
                        </ul>
                        <p
                            v-for="motivo in p.excessos"
                            :key="motivo"
                            class="mt-4 rounded-md border border-warn-line bg-warn-subtle px-3 py-2 text-sm text-warn"
                        >
                            {{ motivo }}
                        </p>
                        <p v-if="!p.excessos.length && acimaDoLimite(p)" class="mt-4 rounded-md border border-warn-line bg-warn-subtle px-3 py-2 text-sm text-warn">O plano {{ p.nome }} permite até {{ usuarios(p.limites.usuarios) }}.</p>
                        <AppButton
                            class="mt-5"
                            :variante="
                                p.destaque || (a.plano === p.chave && emVigor)
                                    ? 'primary'
                                    : 'secondary'
                            "
                            :disabled="p.excessos.length > 0 || acimaDoLimite(p) || consultando !== null"
                            :carregando="(form.processing && form.plano === p.chave) || consultando === p.chave"
                            @click="contratar(p)"
                        >
                            {{ rotuloDoBotao(p) }}
                        </AppButton>
                    </article>
                </div>
                <p v-if="erroDaEscolha || form.errors.plano || form.errors.assentos" class="mt-3 text-sm text-danger" role="alert">{{ erroDaEscolha || form.errors.plano || form.errors.assentos }}</p>
            </section>

            <section v-if="faturas.length" class="mt-8" data-faturas>
                <SectionHeader
                    titulo="Faturas"
                    descricao="As 12 mais recentes."
                />
                <div
                    class="mt-3 overflow-x-auto rounded-lg border border-line bg-bg-1"
                >
                    <table class="w-full min-w-[560px] text-left text-md">
                        <thead
                            class="border-b border-line bg-bg-2 text-sm text-fg-2"
                        >
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Fatura</th>
                                <th class="px-4 py-2.5 font-medium">Plano</th>
                                <th class="px-4 py-2.5 text-right font-medium">
                                    Valor
                                </th>
                                <th class="px-4 py-2.5 font-medium">
                                    Vencimento
                                </th>
                                <th class="px-4 py-2.5 font-medium">
                                    Situação
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in faturas"
                                :key="f.id"
                                class="border-b border-line last:border-0"
                            >
                                <td class="num px-4 py-2.5 text-fg-1">
                                    #{{ f.id }}
                                </td>
                                <td class="px-4 py-2.5">
                                    {{ f.plano_nome }} · {{ f.ciclo }} ·
                                    {{ usuarios(f.usuarios) }}
                                </td>
                                <td class="num px-4 py-2.5 text-right">
                                    {{ formatBRL(f.valor) }}
                                </td>
                                <td class="num px-4 py-2.5 text-fg-1">
                                    {{ formatarData(f.vence_em) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <AppBadge :tom="situacaoDaFatura(f).tom">{{
                                        situacaoDaFatura(f).rotulo
                                    }}</AppBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </PageContainer>
    </AuthenticatedLayout>
</template>
