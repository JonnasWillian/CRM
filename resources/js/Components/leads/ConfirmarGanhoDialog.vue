<script setup>
import { ref, watch } from 'vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import FormField from '@/Components/ui/FormField.vue';

/**
 * Marcar como ganho: confirma e pede o valor fechado (pré-preenchido com o
 * estimado). Devolve { valor_fechado } em `confirmar`; quem chamou grava.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    nome: { type: String, default: '' },
    valorEstimado: { type: [Number, String], default: null },
    projetosAbertos: { type: Number, default: 0 },
    salvando: { type: Boolean, default: false },
    erro: { type: String, default: '' },
});

const emit = defineEmits(['confirmar', 'cancelar']);

const valor = ref(null);

watch(
    () => props.aberto,
    (aberto) => {
        if (aberto) valor.value = props.valorEstimado !== null && props.valorEstimado !== '' ? Number(props.valorEstimado) : null;
    },
    { immediate: true },
);
</script>

<template>
    <AppModal :aberto="aberto" :titulo="`Marcar “${nome}” como ganho?`" tamanho="sm" :persistente="salvando" @fechar="emit('cancelar')">
        <p class="text-md text-fg-1">O lead sai dos ativos e entra nos fechados do período. Dá para mover de volta depois.</p>
        <div class="mt-4 space-y-4">
            <FormField rotulo="Valor fechado" dica="Pré-preenchido com o valor estimado; ajuste se o negócio fechou por outro valor.">
                <AppMoneyInput v-model="valor" />
            </FormField>
            <AppAlert v-if="projetosAbertos > 0" tom="warn">
                Este lead tem {{ projetosAbertos }} projeto{{ projetosAbertos === 1 ? '' : 's' }} em aberto. Eles continuam abertos; feche-os na aba Projetos se for o caso.
            </AppAlert>
            <AppAlert v-if="erro" tom="danger">{{ erro }}</AppAlert>
        </div>
        <template #rodape>
            <AppButton variante="secondary" :disabled="salvando" @click="emit('cancelar')">Cancelar</AppButton>
            <AppButton :carregando="salvando" @click="emit('confirmar', { valor_fechado: valor })">Marcar como ganho</AppButton>
        </template>
    </AppModal>
</template>
