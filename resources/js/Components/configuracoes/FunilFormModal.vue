<script setup>
import { reactive, ref, watch } from 'vue';
import axios from 'axios';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';

/** Novo funil ou edição (nome e descrição). Emite `salvo` com o funil devolvido pela API. */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    funil: { type: Object, default: null },
});
const emit = defineEmits(['fechar', 'salvo']);
const toast = useToast();

const form = reactive({ nome: '', descricao: '' });
const erros = ref({});
const salvando = ref(false);

watch(() => props.aberto, (aberto) => {
    if (!aberto) return;
    erros.value = {};
    Object.assign(form, { nome: props.funil?.nome ?? '', descricao: props.funil?.descricao ?? '' });
}, { immediate: true });

async function salvar() {
    salvando.value = true;
    erros.value = {};
    try {
        const { data } = props.funil
            ? await axios.put(route('api.funis.update', props.funil.id), form, { silencioso: true })
            : await axios.post(route('api.funis.store'), form, { silencioso: true });
        toast.sucesso(props.funil ? 'Funil salvo.' : `Funil “${data.nome}” criado.`);
        emit('salvo', data);
        emit('fechar');
    } catch (e) {
        erros.value = errosDeValidacao(e);
        if (!Object.keys(erros.value).length) toast.erro(mensagemDeErro(e, 'Não foi possível salvar o funil.'));
    } finally {
        salvando.value = false;
    }
}
</script>

<template>
    <AppModal :aberto="aberto" :titulo="funil ? 'Editar funil' : 'Novo funil'" tamanho="sm" @fechar="emit('fechar')">
        <form id="form-funil" class="space-y-4" @submit.prevent="salvar">
            <FormField rotulo="Nome" obrigatorio :erro="erros.nome">
                <AppInput v-model="form.nome" :maxlength="100" placeholder="Ex.: Vendas, Pós-venda" autofocus />
            </FormField>
            <FormField rotulo="Descrição" :erro="erros.descricao" dica="Opcional. Aparece só nesta tela.">
                <AppInput v-model="form.descricao" :maxlength="255" />
            </FormField>
        </form>
        <template #rodape>
            <AppButton variante="secondary" @click="emit('fechar')">Cancelar</AppButton>
            <AppButton type="submit" form="form-funil" :carregando="salvando" :disabled="!form.nome.trim()">{{ funil ? 'Salvar' : 'Criar funil' }}</AppButton>
        </template>
    </AppModal>
</template>
