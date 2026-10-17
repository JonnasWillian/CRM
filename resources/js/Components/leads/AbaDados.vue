<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppDateInput from '@/Components/ui/AppDateInput.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import { useToast } from '@/Composables/useToast';
import { useUnsavedChanges } from '@/Composables/useUnsavedChanges';
import { errosDeValidacao } from '@/utils/erros';
import { ehTelefoneBrasileiro, formatarTelefone, MASCARA_TELEFONE } from '@/utils/telefone';

/**
 * Dados cadastrais e de negócio do lead. Funil e estágio NÃO estão aqui:
 * transição de negócio vive no badge do cabeçalho, com as regras de perda e
 * ganho — misturar com "salvar telefone" disparava o modal de perda num
 * botão "Salvar dados".
 */
const props = defineProps({ lead: { type: Object, required: true } });
const emit = defineEmits(['atualizado']);

const toast = useToast();
const limites = usePage().props.limites ?? {};

const montar = (l) => ({
    nome: l.nome ?? '',
    email: l.email ?? '',
    telefone: formatarTelefone(l.telefone),
    descricao: l.descricao ?? '',
    valor_estimado: l.valor_estimado !== null && l.valor_estimado !== undefined ? Number(l.valor_estimado) : null,
    probabilidade: l.probabilidade ?? null,
    previsao_fechamento: l.previsao_fechamento ?? null,
});

const form = reactive(montar(props.lead));
let base = JSON.stringify(form);
const erros = ref({});
const salvando = ref(false);

const sujo = computed(() => JSON.stringify(form) !== base);
const guarda = useUnsavedChanges(() => sujo.value);

watch(() => props.lead, (l) => {
    if (sujo.value) return;
    Object.assign(form, montar(l));
    base = JSON.stringify(form);
});

async function salvar() {
    salvando.value = true;
    erros.value = {};
    try {
        const { data } = await axios.put(route('api.leads.update', props.lead.id), {
            ...form,
            probabilidade: form.probabilidade === '' ? null : form.probabilidade,
        }, { silencioso: true });
        emit('atualizado', data.data);
        Object.assign(form, montar(data.data));
        base = JSON.stringify(form);
        guarda.rearmar();
        toast.sucesso('Dados salvos.');
    } catch (e) {
        erros.value = errosDeValidacao(e);
        if (!Object.keys(erros.value).length) toast.erro('Não foi possível salvar. Tente novamente.');
    } finally {
        salvando.value = false;
    }
}

function descartar() {
    Object.assign(form, JSON.parse(base));
    erros.value = {};
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="salvar">
        <AppCard>
            <SectionHeader titulo="Contato" />
            <div class="grid gap-4 md:grid-cols-2">
                <FormField rotulo="Nome" obrigatorio :erro="erros.nome">
                    <AppInput v-model="form.nome" limite="nome" autocomplete="off" />
                </FormField>
                <FormField rotulo="E-mail" obrigatorio :erro="erros.email">
                    <AppInput v-model="form.email" type="email" limite="email" autocomplete="off" />
                </FormField>
                <FormField rotulo="Telefone" :erro="erros.telefone" dica="Número internacional: comece com +.">
                    <AppInput v-model="form.telefone" :mascara="ehTelefoneBrasileiro(form.telefone) ? MASCARA_TELEFONE : null" inputmode="tel" placeholder="(00) 00000-0000" />
                </FormField>
                <FormField rotulo="Descrição" :erro="erros.descricao" class="md:col-span-2">
                    <AppTextarea v-model="form.descricao" :rows="3" limite="descricao" mostrar-contador placeholder="Observações gerais" />
                </FormField>
            </div>
        </AppCard>

        <AppCard>
            <SectionHeader titulo="Negócio" descricao="Alimenta os totais do pipeline e a previsão do dashboard." />
            <div class="grid gap-4 md:grid-cols-3">
                <FormField rotulo="Valor estimado" :erro="erros.valor_estimado">
                    <AppMoneyInput v-model="form.valor_estimado" />
                </FormField>
                <FormField rotulo="Probabilidade (%)" :erro="erros.probabilidade" dica="De 0 a 100. O estágio pode sugerir um valor.">
                    <AppInput v-model="form.probabilidade" type="number" min="0" max="100" step="1" inputmode="numeric" />
                </FormField>
                <FormField rotulo="Previsão de fechamento" :erro="erros.previsao_fechamento">
                    <AppDateInput v-model="form.previsao_fechamento" />
                </FormField>
            </div>
        </AppCard>

        <div class="flex items-center justify-end gap-2">
            <AppButton variante="secondary" :disabled="!sujo || salvando" @click="descartar">Descartar</AppButton>
            <AppButton type="submit" :disabled="!sujo" :carregando="salvando">Salvar alterações</AppButton>
        </div>
    </form>
</template>
