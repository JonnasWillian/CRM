<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ArrowRight } from "lucide-vue-next";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import AppButton from "@/Components/ui/AppButton.vue";
import AppCheckbox from "@/Components/ui/AppCheckbox.vue";
import AppInput from "@/Components/ui/AppInput.vue";
import AppSelect from "@/Components/ui/AppSelect.vue";
import FilterChips from "@/Components/ui/FilterChips.vue";
import { ehTelefoneBrasileiro, MASCARA_TELEFONE } from "@/utils/telefone";

/**
 * Questionário de primeiro acesso, em 4 passos curtos. As respostas montam a
 * empresa (estágios do ramo, modelos de tarefa por canal, origens…): ver
 * PersonalizarEmpresa. Cada passo valida antes de avançar; o servidor
 * valida tudo de novo ao concluir. "Pular e usar o padrão" é definitivo.
 */
const props = defineProps({
    opcoes: { type: Object, required: true }, // { experiencias: [{valor, rotulo}], cargos, ramos, tamanhos, para_quem, canais, origens, conheceu }
    nome: { type: String, default: "" },
});

const TOTAL = 4;
const passo = ref(1);
const form = useForm({
    experiencia: null,
    cargo: "",
    ramo: "",
    tamanho: null,
    para_quem: "",
    canais: [],
    origens: [],
    conheceu: "",
    telefone: "",
    dicas_whatsapp: false,
});

// Em que passo está cada campo, e a mensagem quando falta (as mesmas do servidor).
const OBRIGATORIOS = {
    1: { experiencia: "Conte sua experiência com CRM.", cargo: "Informe o seu cargo." },
    2: { ramo: "Informe o ramo de atividade da sua empresa.", tamanho: "Informe quantas pessoas vão usar o sistema." },
    3: { para_quem: "Informe para quem você vende.", canais: "Escolha ao menos um canal.", origens: "Escolha ao menos uma origem." },
    4: {},
};
const PASSO_DO_CAMPO = { experiencia: 1, cargo: 1, ramo: 2, tamanho: 2, para_quem: 3, canais: 3, origens: 3, conheceu: 4, telefone: 4, dicas_whatsapp: 4 };

const errosLocais = ref({});
const erro = (campo) => errosLocais.value[campo] ?? form.errors[campo] ?? null;
const vazio = (v) => v === null || v === "" || (Array.isArray(v) && v.length === 0);

function validarPasso() {
    const faltando = {};
    for (const [campo, mensagem] of Object.entries(OBRIGATORIOS[passo.value])) {
        if (vazio(form[campo])) faltando[campo] = mensagem;
    }
    errosLocais.value = faltando;
    return Object.keys(faltando).length === 0;
}

function prosseguir() {
    if (!validarPasso()) return;
    if (passo.value < TOTAL) {
        passo.value += 1;
        return;
    }
    form.transform((d) => ({ ...d, conheceu: d.conheceu || null, telefone: d.telefone || null })).post(route("boas-vindas.store"), {
        // Erro do servidor: volta ao primeiro passo que tem o campo com problema.
        onError: (erros) => {
            const passos = Object.keys(erros).map((c) => PASSO_DO_CAMPO[c.split(".")[0]] ?? TOTAL);
            passo.value = Math.min(...passos, TOTAL);
        },
    });
}

function voltar() {
    errosLocais.value = {};
    passo.value = Math.max(1, passo.value - 1);
}

const pular = () => router.post(route("boas-vindas.pular"));
const ultimo = computed(() => passo.value === TOTAL);
</script>

<template>
    <Head title="Boas-vindas" />
    <AuthLayout largo>
        <div class="mb-6 flex items-center justify-between gap-3">
            <span class="num rounded-full bg-primary-subtle px-3 py-1 text-xs font-semibold text-primary">Passo {{ passo }} de {{ TOTAL }}</span>
            <div class="flex gap-1.5" aria-hidden="true">
                <span v-for="n in TOTAL" :key="n" class="h-1.5 w-8 rounded-full transition-colors duration-2 ease-ui" :class="n <= passo ? 'bg-primary' : 'bg-line'" />
            </div>
        </div>

        <form class="space-y-7" novalidate @submit.prevent="prosseguir">
            <!-- Passo 1 -->
            <template v-if="passo === 1">
                <p class="text-md leading-relaxed text-fg-1">
                    Olá{{ nome ? `, ${nome}` : "" }}! Conte um pouco sobre você e a empresa: com as respostas, o sistema já vem montado do seu jeito.
                </p>
                <fieldset>
                    <legend class="mb-3 font-display text-lg font-semibold text-fg-0">Qual sua experiência com CRM?</legend>
                    <div role="radiogroup" aria-label="Experiência com CRM" class="overflow-hidden rounded border" :class="erro('experiencia') ? 'border-danger' : 'border-line'">
                        <button
                            v-for="o in opcoes.experiencias"
                            :key="o.valor"
                            type="button"
                            role="radio"
                            :aria-checked="form.experiencia === o.valor ? 'true' : 'false'"
                            class="flex w-full items-center gap-3 border-b border-line px-4 py-3.5 text-left text-md transition-colors duration-1 ease-ui last:border-b-0"
                            :class="form.experiencia === o.valor ? 'bg-primary-subtle font-medium text-primary' : 'text-fg-1 hover:bg-bg-2 hover:text-fg-0'"
                            @click="form.experiencia = o.valor"
                        >
                            <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border-2" :class="form.experiencia === o.valor ? 'border-primary' : 'border-line-2'" aria-hidden="true">
                                <span v-if="form.experiencia === o.valor" class="h-2 w-2 rounded-full bg-primary" />
                            </span>
                            {{ o.rotulo }}
                        </button>
                    </div>
                    <p v-if="erro('experiencia')" role="alert" class="mt-2 text-sm text-danger">{{ erro("experiencia") }}</p>
                </fieldset>
                <div>
                    <label for="pergunta-cargo" class="mb-3 block font-display text-lg font-semibold text-fg-0">Qual seu cargo?</label>
                    <AppSelect id="pergunta-cargo" v-model="form.cargo" :opcoes="opcoes.cargos" placeholder="Selecione" />
                    <p v-if="erro('cargo')" role="alert" class="mt-2 text-sm text-danger">{{ erro("cargo") }}</p>
                </div>
            </template>

            <!-- Passo 2 -->
            <template v-else-if="passo === 2">
                <div>
                    <label for="pergunta-ramo" class="mb-3 block font-display text-lg font-semibold text-fg-0">Qual o ramo da sua empresa?</label>
                    <AppSelect id="pergunta-ramo" v-model="form.ramo" :opcoes="opcoes.ramos" placeholder="Selecione" />
                    <p v-if="erro('ramo')" role="alert" class="mt-2 text-sm text-danger">{{ erro("ramo") }}</p>
                    <p v-else class="mt-2 text-sm text-fg-2">O funil já vem com as etapas mais comuns do seu ramo. Dá para mudar depois.</p>
                </div>
                <fieldset>
                    <legend class="mb-3 font-display text-lg font-semibold text-fg-0">Quantas pessoas da empresa vão usar o sistema?</legend>
                    <FilterChips v-model="form.tamanho" :opcoes="opcoes.tamanhos" />
                    <p v-if="erro('tamanho')" role="alert" class="mt-2 text-sm text-danger">{{ erro("tamanho") }}</p>
                </fieldset>
            </template>

            <!-- Passo 3 -->
            <template v-else-if="passo === 3">
                <div>
                    <label for="pergunta-para-quem" class="mb-3 block font-display text-lg font-semibold text-fg-0">Para quem você vende?</label>
                    <AppSelect id="pergunta-para-quem" v-model="form.para_quem" :opcoes="opcoes.para_quem" placeholder="Selecione" />
                    <p v-if="erro('para_quem')" role="alert" class="mt-2 text-sm text-danger">{{ erro("para_quem") }}</p>
                </div>
                <fieldset>
                    <legend class="mb-3 font-display text-lg font-semibold text-fg-0">Como você fala com seus leads e clientes?</legend>
                    <FilterChips v-model="form.canais" :opcoes="opcoes.canais" multiplo />
                    <p v-if="erro('canais')" role="alert" class="mt-2 text-sm text-danger">{{ erro("canais") }}</p>
                    <p v-else class="mt-2 text-sm text-fg-2">Marque quantos quiser.</p>
                </fieldset>
                <fieldset>
                    <legend class="mb-3 font-display text-lg font-semibold text-fg-0">De onde vêm seus leads?</legend>
                    <FilterChips v-model="form.origens" :opcoes="opcoes.origens" multiplo />
                    <p v-if="erro('origens')" role="alert" class="mt-2 text-sm text-danger">{{ erro("origens") }}</p>
                </fieldset>
            </template>

            <!-- Passo 4 -->
            <template v-else>
                <div>
                    <label for="pergunta-conheceu" class="mb-3 block font-display text-lg font-semibold text-fg-0">Como você conheceu o UserFlow?</label>
                    <AppSelect id="pergunta-conheceu" v-model="form.conheceu" :opcoes="opcoes.conheceu" placeholder="Selecione (opcional)" />
                </div>
                <div>
                    <label for="pergunta-telefone" class="mb-3 block font-display text-lg font-semibold text-fg-0">Qual seu celular ou WhatsApp?</label>
                    <AppInput id="pergunta-telefone" v-model="form.telefone" :mascara="ehTelefoneBrasileiro(form.telefone) ? MASCARA_TELEFONE : null" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" />
                    <p v-if="erro('telefone')" role="alert" class="mt-2 text-sm text-danger">{{ erro("telefone") }}</p>
                    <div class="mt-3">
                        <AppCheckbox v-model="form.dicas_whatsapp" rotulo="Quero receber dicas pelo WhatsApp" />
                    </div>
                </div>
            </template>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="self-start text-sm font-medium text-fg-2 underline-offset-2 hover:text-fg-0 hover:underline" @click="pular">Pular e usar o padrão</button>
                <div class="flex gap-2 sm:justify-end">
                    <AppButton v-if="passo > 1" variante="secondary" class="flex-1 sm:flex-none" @click="voltar">Voltar</AppButton>
                    <AppButton class="flex-1 sm:flex-none" :carregando="form.processing" @click="prosseguir">
                        {{ ultimo ? "Concluir" : "Prosseguir" }}
                        <template #iconeDireita><ArrowRight /></template>
                    </AppButton>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>
