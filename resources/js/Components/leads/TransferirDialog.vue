<script setup>
import { computed, ref, watch } from 'vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import FormField from '@/Components/ui/FormField.vue';

/**
 * Escolher o novo responsável de 1 ou N leads. O destino é um membro ativo
 * (a lista já vem filtrada do servidor); o responsável atual some da lista
 * quando a transferência é de um lead só.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    agentes: { type: Array, required: true }, // [{ id, name }]
    atualId: { type: Number, default: null },
    rotuloConfirmar: { type: String, default: 'Transferir' },
    salvando: { type: Boolean, default: false },
    erro: { type: String, default: '' },
});
const emit = defineEmits(['confirmar', 'cancelar']);

const destino = ref(null);
const opcoes = computed(() => props.agentes.filter((a) => a.id !== props.atualId).map((a) => ({ valor: a.id, rotulo: a.name })));
watch(() => props.aberto, (v) => { if (v) destino.value = null; });
</script>

<template>
    <AppModal :aberto="aberto" :titulo="titulo" :descricao="descricao ?? 'O lead sai da carteira atual; o histórico e as tarefas seguem com ele.'" tamanho="sm" @fechar="emit('cancelar')">
        <form id="form-transferir" class="space-y-4" @submit.prevent="destino && emit('confirmar', { user_id: destino })">
            <FormField rotulo="Novo responsável" obrigatorio>
                <AppSelect v-model="destino" :opcoes="opcoes" placeholder="Escolha um membro" />
            </FormField>
            <AppAlert v-if="erro" tom="danger">{{ erro }}</AppAlert>
        </form>
        <template #rodape>
            <AppButton variante="secondary" @click="emit('cancelar')">Cancelar</AppButton>
            <AppButton type="submit" form="form-transferir" :carregando="salvando" :disabled="!destino">{{ rotuloConfirmar }}</AppButton>
        </template>
    </AppModal>
</template>
