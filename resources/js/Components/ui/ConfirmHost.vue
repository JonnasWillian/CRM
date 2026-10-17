<script setup>
import { computed, ref, watch } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import AppButton from './AppButton.vue';
import AppInput from './AppInput.vue';
import AppModal from './AppModal.vue';
import FormField from './FormField.vue';

/**
 * Renderiza o pedido de `useConfirm()`. Montado uma vez no layout.
 * Em ações destrutivas o foco inicial vai para "Cancelar" — Enter por
 * reflexo não apaga nada.
 */
const { pendente, responder } = useConfirm();
const digitado = ref('');

watch(pendente, () => {
    digitado.value = '';
});

const liberado = computed(() => !pendente.value?.requerDigitacao || digitado.value.trim() === pendente.value.requerDigitacao);
</script>

<template>
    <AppModal
        :aberto="pendente !== null"
        :titulo="pendente?.titulo ?? ''"
        tamanho="sm"
        :persistente="Boolean(pendente?.requerDigitacao)"
        @fechar="responder(false)"
    >
        <template v-if="pendente">
            <p v-if="pendente.mensagem" class="text-md text-fg-1">{{ pendente.mensagem }}</p>
            <p v-if="pendente.detalhe" class="mt-2 text-sm text-fg-2">{{ pendente.detalhe }}</p>
            <FormField v-if="pendente.requerDigitacao" :rotulo="`Digite ${pendente.requerDigitacao} para confirmar`" class="mt-4">
                <AppInput v-model="digitado" autofocus autocomplete="off" />
            </FormField>
        </template>
        <template v-if="pendente" #rodape>
            <AppButton variante="secondary" :autofocus="pendente.tom === 'danger' && !pendente.requerDigitacao" @click="responder(false)">
                {{ pendente.rotuloCancelar }}
            </AppButton>
            <AppButton :variante="pendente.tom === 'danger' ? 'danger' : 'primary'" :disabled="!liberado" @click="responder(true)">
                {{ pendente.rotuloConfirmar }}
            </AppButton>
        </template>
    </AppModal>
</template>
