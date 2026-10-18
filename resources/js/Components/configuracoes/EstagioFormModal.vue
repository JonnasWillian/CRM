<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { Check } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';

/**
 * Novo estágio ou edição. A cor é categórica e só para estágios abertos:
 * ganho e perdido usam as cores de status (verde/vermelho) do sistema.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    funilId: { type: Number, required: true },
    estagio: { type: Object, default: null },
});
const emit = defineEmits(['fechar', 'salvo']);
const toast = useToast();

// Paleta categórica do design system (Anexo B): azul, mostarda, roxo, magenta, cinza.
const PALETA = ['#2b6cb0', '#9c6612', '#6b46c1', '#b83280', '#4a5568'];
const TIPOS = [
    { valor: 'aberto', rotulo: 'Aberto (em andamento)' },
    { valor: 'ganho', rotulo: 'Ganho (fechamento positivo)' },
    { valor: 'perdido', rotulo: 'Perdido (fechamento negativo)' },
];

const form = reactive({ descricao: '', tipo: 'aberto', cor: PALETA[0], probabilidade_padrao: null });
const erros = ref({});
const salvando = ref(false);
const aberto = computed(() => form.tipo === 'aberto');

watch(() => props.aberto, (v) => {
    if (!v) return;
    erros.value = {};
    Object.assign(form, {
        descricao: props.estagio?.descricao ?? '',
        tipo: props.estagio?.tipo ?? 'aberto',
        cor: props.estagio?.cor ?? PALETA[0],
        probabilidade_padrao: props.estagio?.probabilidade_padrao ?? null,
    });
}, { immediate: true });

async function salvar() {
    salvando.value = true;
    erros.value = {};
    const payload = {
        descricao: form.descricao,
        tipo: form.tipo,
        cor: aberto.value ? form.cor : null,
        probabilidade_padrao: aberto.value && form.probabilidade_padrao !== '' ? form.probabilidade_padrao : null,
    };
    try {
        const { data } = props.estagio
            ? await axios.put(route('api.estagios.update', props.estagio.id), payload, { silencioso: true })
            : await axios.post(route('api.funis.estagios.store', props.funilId), payload, { silencioso: true });
        toast.sucesso(props.estagio ? 'Estágio salvo.' : `Estágio “${data.descricao}” criado.`);
        emit('salvo', data);
        emit('fechar');
    } catch (e) {
        erros.value = errosDeValidacao(e);
        if (!Object.keys(erros.value).length) toast.erro(mensagemDeErro(e, 'Não foi possível salvar o estágio.'));
    } finally {
        salvando.value = false;
    }
}
</script>

<template>
    <AppModal :aberto="props.aberto" :titulo="estagio ? 'Editar estágio' : 'Novo estágio'" tamanho="sm" @fechar="emit('fechar')">
        <form id="form-estagio" class="space-y-4" @submit.prevent="salvar">
            <FormField rotulo="Nome" obrigatorio :erro="erros.descricao">
                <AppInput v-model="form.descricao" :maxlength="255" placeholder="Ex.: Proposta enviada" autofocus />
            </FormField>
            <FormField rotulo="Tipo" obrigatorio :erro="erros.tipo" dica="Ganho e perdido fecham o lead; a cor deles é a do sistema.">
                <AppSelect v-model="form.tipo" :opcoes="TIPOS" />
            </FormField>
            <FormField v-if="aberto" rotulo="Cor" :erro="erros.cor">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Cor do estágio">
                    <button
                        v-for="c in PALETA"
                        :key="c"
                        type="button"
                        role="radio"
                        :aria-checked="form.cor === c ? 'true' : 'false'"
                        :aria-label="`Cor ${c}`"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border-2 transition-transform duration-1 ease-ui hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
                        :class="form.cor === c ? 'border-fg-0' : 'border-transparent'"
                        :style="{ background: c }"
                        @click="form.cor = c"
                    >
                        <Check v-if="form.cor === c" :size="16" class="text-white" aria-hidden="true" />
                    </button>
                </div>
            </FormField>
            <FormField v-if="aberto" rotulo="Probabilidade padrão (%)" :erro="erros.probabilidade_padrao" dica="Pré-preenche a probabilidade de leads novos neste estágio quando o campo está vazio.">
                <AppInput v-model="form.probabilidade_padrao" type="number" :min="0" :max="100" :step="5" inputmode="numeric" placeholder="—" />
            </FormField>
        </form>
        <template #rodape>
            <AppButton variante="secondary" @click="emit('fechar')">Cancelar</AppButton>
            <AppButton type="submit" form="form-estagio" :carregando="salvando" :disabled="!form.descricao.trim()">{{ estagio ? 'Salvar' : 'Criar estágio' }}</AppButton>
        </template>
    </AppModal>
</template>
