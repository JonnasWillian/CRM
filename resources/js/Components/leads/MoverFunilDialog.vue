<script setup>
import { computed, ref, watch } from 'vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import FormField from '@/Components/ui/FormField.vue';

/** Escolhe funil e estágio de destino; devolve { funil_id, estagio_id } em `confirmar`. */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    nome: { type: String, default: '' },
    funis: { type: Array, required: true }, // [{ id, nome, estagios: [{ id, descricao, tipo }] }]
    funilAtualId: { type: Number, default: null },
    salvando: { type: Boolean, default: false },
    erro: { type: String, default: '' },
});

const emit = defineEmits(['confirmar', 'cancelar']);

const funilId = ref(null);
const estagioId = ref(null);

const funisDisponiveis = computed(() => props.funis.filter((f) => f.id !== props.funilAtualId));
const estagios = computed(() => props.funis.find((f) => f.id === funilId.value)?.estagios ?? []);

watch(
    () => props.aberto,
    (aberto) => {
        if (!aberto) return;
        funilId.value = funisDisponiveis.value[0]?.id ?? null;
    },
    { immediate: true },
);

watch(funilId, () => {
    estagioId.value = estagios.value.find((e) => e.tipo === 'aberto')?.id ?? estagios.value[0]?.id ?? null;
}, { immediate: true });
</script>

<template>
    <AppModal :aberto="aberto" :titulo="`Mover “${nome}” de funil`" tamanho="sm" :persistente="salvando" @fechar="emit('cancelar')">
        <div v-if="funisDisponiveis.length === 0" class="text-md text-fg-1">Não há outro funil para onde mover. Crie um em Configurações › Funis.</div>
        <div v-else class="space-y-4">
            <FormField rotulo="Funil de destino" obrigatorio>
                <AppSelect v-model="funilId" :opcoes="funisDisponiveis.map((f) => ({ valor: f.id, rotulo: f.nome }))" />
            </FormField>
            <FormField rotulo="Estágio de entrada" obrigatorio>
                <AppSelect v-model="estagioId" :opcoes="estagios.map((e) => ({ valor: e.id, rotulo: e.descricao }))" />
            </FormField>
            <AppAlert v-if="erro" tom="danger">{{ erro }}</AppAlert>
        </div>
        <template #rodape>
            <AppButton variante="secondary" :disabled="salvando" @click="emit('cancelar')">Cancelar</AppButton>
            <AppButton :carregando="salvando" :disabled="!funilId || !estagioId" @click="emit('confirmar', { funil_id: funilId, estagio_id: estagioId })">Mover</AppButton>
        </template>
    </AppModal>
</template>
