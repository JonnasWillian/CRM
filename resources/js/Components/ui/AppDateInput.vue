<script setup>
import { BORDA_CAMPO, CLASSES_CAMPO, TAMANHO_CAMPO, useCampo } from './campo';

/** <input type="date">, valor ISO YYYY-MM-DD (o que o backend valida com `date`). */
const props = defineProps({
    modelValue: { type: String, default: null },
    min: { type: String, default: null },
    max: { type: String, default: null },
    tamanho: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    name: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { invalido, atributos } = useCampo(props);
</script>

<template>
    <input
        v-bind="atributos"
        type="date"
        :value="modelValue ?? ''"
        :min="min ?? undefined"
        :max="max ?? undefined"
        :disabled="disabled"
        :name="name"
        class="num"
        :class="[CLASSES_CAMPO, BORDA_CAMPO(invalido), TAMANHO_CAMPO[tamanho] ?? TAMANHO_CAMPO.md]"
        @input="emit('update:modelValue', $event.target.value || null)"
    />
</template>
