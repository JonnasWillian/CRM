<script setup>
import ModalMotivoPerda from '@/Components/ModalMotivoPerda.vue';
import { usePerda } from '@/Composables/usePerda';

/** Instância única do modal de motivo de perda; montada no layout. */
const { pendente, confirmar, cancelar } = usePerda();
</script>

<template>
    <ModalMotivoPerda
        :aberto="pendente !== null"
        :titulo="pendente?.titulo ?? 'Registrar perda'"
        :detalhe="pendente?.detalhe ?? null"
        :erro="pendente?.erro ?? ''"
        :salvando="pendente?.salvando ?? false"
        @confirmar="confirmar"
        @cancelar="cancelar"
    />
</template>
