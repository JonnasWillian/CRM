<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import AppButton from '@/Components/ui/AppButton.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';

/**
 * Pergunta o motivo antes de concluir uma perda. Não escreve nada: devolve
 * { motivo_perda_id, observacao } em `confirmar` e quem chamou decide — o
 * rollback fica com quem conhece o estado a desfazer.
 *
 * Recarrega o catálogo a cada abertura: um motivo arquivado nesse meio-tempo
 * seria recusado pelo backend com um erro que a tela não saberia explicar.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    titulo: { type: String, default: 'Registrar perda' },
    detalhe: { type: String, default: null },
    erro: { type: String, default: '' },
    salvando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'cancelar']);

const MAX_OBSERVACAO = 500;
const motivos = ref([]);
const carregando = ref(false);
const motivoId = ref(null);
const observacao = ref('');
const erroLocal = ref('');

const opcoes = computed(() => motivos.value.map((m) => ({ valor: m.id, rotulo: m.descricao })));

async function buscarMotivos() {
    carregando.value = true;
    erroLocal.value = '';
    try {
        const res = await axios.get(route('api.motivos-perda.index'), { silencioso: true });
        motivos.value = res.data;
        motivoId.value = res.data[0]?.id ?? null;
    } catch {
        erroLocal.value = 'Não foi possível carregar os motivos de perda.';
    } finally {
        carregando.value = false;
    }
}

watch(
    () => props.aberto,
    (aberto) => {
        if (!aberto) return;
        observacao.value = '';
        erroLocal.value = '';
        buscarMotivos();
    },
    { immediate: true },
);

function confirmar() {
    if (!motivoId.value) {
        erroLocal.value = 'Escolha um motivo.';
        return;
    }
    emit('confirmar', { motivo_perda_id: motivoId.value, observacao: observacao.value.trim() || null });
}
</script>

<template>
    <AppModal :aberto="aberto" :titulo="titulo" tamanho="sm" :persistente="salvando" @fechar="emit('cancelar')">
        <p class="text-md text-fg-1">Registrar o motivo é o que permite responder depois por que a empresa perde.</p>
        <p v-if="detalhe" class="mt-2 text-sm text-fg-2">{{ detalhe }}</p>

        <div class="mt-4 space-y-4">
            <FormField rotulo="Motivo" obrigatorio>
                <AppSelect v-model="motivoId" :opcoes="opcoes" :disabled="carregando" :placeholder="carregando ? 'Carregando…' : null" />
            </FormField>
            <FormField rotulo="Detalhe" dica="Opcional. O que aconteceu, em uma frase.">
                <AppTextarea v-model="observacao" :rows="3" :maxlength="MAX_OBSERVACAO" mostrar-contador />
            </FormField>
            <AppAlert v-if="erro || erroLocal" tom="danger">{{ erro || erroLocal }}</AppAlert>
        </div>

        <template #rodape>
            <AppButton variante="secondary" :disabled="salvando" @click="emit('cancelar')">Cancelar</AppButton>
            <AppButton variante="danger" :carregando="salvando" :disabled="carregando || !motivoId" @click="confirmar">Registrar perda</AppButton>
        </template>
    </AppModal>
</template>
