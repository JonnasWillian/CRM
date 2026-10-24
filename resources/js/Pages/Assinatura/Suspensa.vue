<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import { formatarData } from "@/utils/formatadores";

/**
 * Acesso suspenso: trinta dias sem plano pago. Admin regulariza em
 * Assinatura; os demais pedem ao admin. A exportação continua liberada
 * para a empresa nunca ficar refém dos próprios dados.
 */
const props = defineProps({
    assinatura: { type: Object, required: true },
    empresa: { type: String, required: true },
    podeGerenciar: { type: Boolean, default: false },
    contato: { type: Object, default: () => ({}) },
});

const motivo = computed(
    () =>
        ({
            teste_expirado:
                "O período de teste terminou e nenhum plano foi contratado.",
            vencida: `O plano ${props.assinatura.plano_nome} venceu e a renovação não foi paga.`,
            cancelada: "A assinatura foi cancelada.",
        })[props.assinatura.status] ?? "Não há plano pago em vigor.",
);
const whatsapp = computed(() =>
    props.contato?.whatsapp ? `https://wa.me/${props.contato.whatsapp}` : null,
);
</script>

<template>
    <Head title="Acesso suspenso" />
    <AuthLayout
        titulo="Acesso suspenso"
        :descricao="`O acesso da ${empresa} está suspenso desde ${formatarData(assinatura.suspende_em)}.`"
    >
        <p class="text-md text-fg-1">
            {{ motivo }} Os dados continuam guardados e voltam a aparecer assim
            que o pagamento for confirmado.
        </p>

        <div class="mt-6 space-y-3">
            <AppButton
                v-if="podeGerenciar"
                as="Link"
                :href="route('configuracoes.assinatura')"
                bloco
                >Ver planos e faturas</AppButton
            >
            <p
                v-else
                class="rounded-md border border-line bg-bg-2 px-3.5 py-3 text-sm text-fg-1"
            >
                Peça ao administrador da empresa para regularizar o plano em
                Configurações › Assinatura.
            </p>
            <AppButton
                as="a"
                :href="route('api.leads.exportar')"
                variante="secondary"
                bloco
                >Exportar meus leads (CSV)</AppButton
            >
        </div>

        <p v-if="contato?.email || whatsapp" class="mt-6 text-sm text-fg-2">
            Dúvidas sobre a cobrança?
            <a
                v-if="contato?.email"
                :href="`mailto:${contato.email}`"
                class="font-medium text-primary hover:underline"
                >{{ contato.email }}</a
            >
            <template v-if="contato?.email && whatsapp"> ou </template>
            <a
                v-if="whatsapp"
                :href="whatsapp"
                target="_blank"
                rel="noopener"
                class="font-medium text-primary hover:underline"
                >WhatsApp</a
            >
        </p>

        <template #rodape>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="font-medium text-primary hover:underline"
                >Sair</Link
            >
        </template>
    </AuthLayout>
</template>
