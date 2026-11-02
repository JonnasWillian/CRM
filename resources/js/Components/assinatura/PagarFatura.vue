<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import axios from "axios";
import { Check, CircleCheck, Copy, ExternalLink } from "lucide-vue-next";
import AppAlert from "@/Components/ui/AppAlert.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import AppInput from "@/Components/ui/AppInput.vue";
import AppModal from "@/Components/ui/AppModal.vue";
import AppSpinner from "@/Components/ui/AppSpinner.vue";
import FormField from "@/Components/ui/FormField.vue";
import { errosDeValidacao, mensagemDeErro } from "@/utils/erros";
import { formatBRL, formatarHora } from "@/utils/formatadores";

/**
 * Pagar a fatura pendente pelo Asaas: Pix na tela (QR Code e copia e cola)
 * e a página do Asaas para boleto ou cartão. Enquanto aberto, consulta a
 * fatura a cada 5 s, mostra quando conferiu pela última vez e, quando o
 * pagamento cai, troca o QR Code pela confirmação. Se passar 1 minuto sem
 * pagamento, explica o que conferir (Pix para outra chave não paga a fatura).
 * Na primeira vez, pede o CPF/CNPJ de quem paga: o Asaas exige.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    fatura: { type: Object, required: true },
    precisaDocumento: { type: Boolean, default: false },
});
const emit = defineEmits(["fechar", "paga"]);

const CONSULTA_MS = 5000;
const AJUDA_APOS_MS = 60000;

const etapa = ref("documento"); // documento | gerando | pix | paga | erro
const documento = ref("");
const erroDoDocumento = ref(null);
const erro = ref(null);
const pix = ref(null);
const link = ref(null);
const copiado = ref(false);
const conferidoEm = ref(null);
const demorou = ref(false);
const documentoSalvo = ref(false); // salvo na primeira cobrança: reabrir não pede de novo
let consulta = null;
let ajuda = null;
let desmontado = false;
// Fechou ou saiu da página enquanto o Pix era gerado: a resposta não liga mais nada.
const foiEmbora = () => desmontado || !props.aberto;

async function gerar() {
    etapa.value = "gerando";
    erro.value = null;
    erroDoDocumento.value = null;
    try {
        const { data } = await axios.post(
            route("configuracoes.assinatura.pagar", props.fatura.id),
            props.precisaDocumento && !documentoSalvo.value ? { documento: documento.value } : {},
            { silencioso: true }, // o erro aparece aqui dentro, com "Tentar de novo"
        );
        documentoSalvo.value = true;
        if (foiEmbora()) return;
        pix.value = data.pix;
        link.value = data.link;
        etapa.value = "pix";
        acompanhar();
    } catch (e) {
        if (foiEmbora()) return;
        const campos = errosDeValidacao(e);
        if (campos.documento) {
            erroDoDocumento.value = campos.documento;
            etapa.value = "documento";
            return;
        }
        erro.value = mensagemDeErro(e, "Não foi possível gerar o Pix agora. Tente de novo em instantes.");
        etapa.value = "erro";
    }
}

function acompanhar() {
    parar();
    demorou.value = false;
    ajuda = setTimeout(() => (demorou.value = true), AJUDA_APOS_MS);
    consulta = setInterval(async () => {
        try {
            const { data } = await axios.get(route("configuracoes.assinatura.fatura", props.fatura.id), { silencioso: true });
            if (data.conferido_em) conferidoEm.value = data.conferido_em;
            if (data.status === "paga") {
                parar();
                etapa.value = "paga";
            } else if (data.status === "cancelada") {
                parar();
                erro.value = "Esta fatura foi cancelada. Feche e confira a fatura atual.";
                etapa.value = "erro";
            }
        } catch (e) {
            // Sem aviso: é só a consulta de fundo. A próxima, em 5 s, tenta de novo.
        }
    }, CONSULTA_MS);
}

function parar() {
    clearInterval(consulta);
    clearTimeout(ajuda);
    consulta = null;
    ajuda = null;
}

// Depois de pago, fechar de qualquer jeito leva a página ao plano ativo.
function fechar() {
    emit(etapa.value === "paga" ? "paga" : "fechar");
}

async function copiar() {
    try {
        await navigator.clipboard.writeText(pix.value.copia_e_cola);
    } catch (e) {
        // Sem permissão de área de transferência: seleciona para o Ctrl+C.
        document.getElementById("pix-copia-e-cola")?.select();
        return;
    }
    copiado.value = true;
    setTimeout(() => (copiado.value = false), 2500);
}

watch(
    () => props.aberto,
    (aberto) => {
        if (!aberto) {
            parar();
            return;
        }
        pix.value = null;
        erro.value = null;
        conferidoEm.value = null;
        if (props.precisaDocumento && !documentoSalvo.value) etapa.value = "documento";
        else gerar();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    desmontado = true;
    parar();
});
</script>

<template>
    <AppModal
        :aberto="aberto"
        :titulo="`Pagar fatura #${fatura.id}`"
        :descricao="`${formatBRL(fatura.valor)}, plano ${fatura.plano_nome}.`"
        tamanho="sm"
        @fechar="fechar"
    >
        <form v-if="etapa === 'documento'" id="form-documento-pagador" class="space-y-4" @submit.prevent="gerar">
            <p class="text-md text-fg-1">
                Para emitir a cobrança, informe o CPF ou CNPJ de quem paga. Ele fica salvo para as próximas faturas.
            </p>
            <FormField rotulo="CPF ou CNPJ" obrigatorio :erro="erroDoDocumento">
                <!-- Sem máscara: o CNPJ alfanumérico (desde julho de 2026) tem letras. -->
                <AppInput
                    v-model="documento"
                    autocomplete="off"
                    placeholder="00.000.000/0000-00"
                    :maxlength="20"
                    autofocus
                />
            </FormField>
        </form>

        <div v-else-if="etapa === 'gerando'" class="flex flex-col items-center gap-3 py-10 text-md text-fg-2" aria-live="polite">
            <AppSpinner :tamanho="20" />
            Gerando o Pix…
        </div>

        <div v-else-if="etapa === 'pix'" class="flex flex-col items-center text-center" data-pix>
            <img :src="pix.imagem" alt="QR Code do Pix desta fatura" class="h-52 w-52 rounded-md border border-line bg-white p-2" />
            <p class="mt-3 text-md text-fg-1">No app do banco, escolha pagar com Pix e aponte a câmera para o código.</p>

            <label for="pix-copia-e-cola" class="mt-5 w-full text-left text-sm font-medium text-fg-2">Ou copie o código Pix</label>
            <div class="mt-1 flex w-full gap-2">
                <input
                    id="pix-copia-e-cola"
                    :value="pix.copia_e_cola"
                    readonly
                    class="num min-w-0 flex-1 truncate rounded-md border border-line bg-bg-2 px-3 text-sm text-fg-1"
                    @focus="$event.target.select()"
                />
                <AppButton variante="secondary" @click="copiar">
                    <template #icone><component :is="copiado ? Check : Copy" /></template>
                    {{ copiado ? "Copiado" : "Copiar" }}
                </AppButton>
            </div>

            <div class="mt-5 w-full rounded-md border border-line bg-bg-2 px-3 py-2.5 text-left">
                <p class="flex items-center gap-2 text-md font-medium text-fg-0" aria-live="polite">
                    <AppSpinner :tamanho="14" />
                    Aguardando o pagamento
                </p>
                <p class="mt-1 text-sm text-fg-2">
                    Esta tela confere o pagamento a cada poucos segundos e muda sozinha quando o Pix cair.
                    <template v-if="conferidoEm"> Última conferência às {{ formatarHora(conferidoEm) }}.</template>
                </p>
            </div>

            <AppAlert v-if="demorou" class="mt-3 w-full text-left" titulo="Pagou e ainda não confirmou?">
                O Pix costuma cair em segundos. Confira no comprovante se você pagou por este QR Code ou por este código:
                Pix enviado para outra chave não paga a fatura. Alguns bancos seguram o primeiro Pix para um destinatário
                novo por alguns minutos.
            </AppAlert>
        </div>

        <div v-else-if="etapa === 'paga'" class="flex flex-col items-center py-6 text-center" role="status" data-pago>
            <CircleCheck :size="44" class="text-ok" aria-hidden="true" />
            <p class="mt-3 font-display text-xl font-semibold text-fg-0">Pagamento confirmado</p>
            <p class="mt-1 text-md text-fg-1">
                Recebemos {{ formatBRL(fatura.valor) }}. O plano {{ fatura.plano_nome }} já está ativo.
            </p>
        </div>

        <AppAlert v-else tom="danger">{{ erro }}</AppAlert>

        <template #rodape>
            <template v-if="etapa === 'documento'">
                <AppButton variante="ghost" @click="emit('fechar')">Cancelar</AppButton>
                <AppButton type="submit" form="form-documento-pagador">Gerar Pix</AppButton>
            </template>
            <template v-else-if="etapa === 'pix'">
                <AppButton v-if="link" as="a" :href="link" target="_blank" rel="noopener" variante="ghost">
                    Pagar com boleto ou cartão
                    <template #iconeDireita><ExternalLink /></template>
                </AppButton>
                <AppButton variante="secondary" @click="emit('fechar')">Fechar</AppButton>
            </template>
            <template v-else-if="etapa === 'paga'">
                <AppButton @click="emit('paga')">Concluir</AppButton>
            </template>
            <template v-else-if="etapa === 'erro'">
                <AppButton variante="ghost" @click="emit('fechar')">Fechar</AppButton>
                <AppButton @click="gerar">Tentar de novo</AppButton>
            </template>
        </template>
    </AppModal>
</template>
