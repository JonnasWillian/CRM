<script setup>
import { onMounted, ref, watch } from 'vue';
import { useCampo } from './campo';

/** Checkbox nativa; `modelValue` booleano ou array (com `valor`); `indeterminado` para o cabeçalho tri-state. */
const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    valor: { type: [String, Number], default: null },
    rotulo: { type: String, default: null },
    indeterminado: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    ariaLabel: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { atributos } = useCampo(props);
const el = ref(null);

const marcada = () => (Array.isArray(props.modelValue) ? props.modelValue.includes(props.valor) : props.modelValue);

function aoMudar(e) {
    if (Array.isArray(props.modelValue)) {
        const lista = new Set(props.modelValue);
        e.target.checked ? lista.add(props.valor) : lista.delete(props.valor);
        emit('update:modelValue', [...lista]);
    } else {
        emit('update:modelValue', e.target.checked);
    }
}

const aplicarIndeterminado = () => {
    if (el.value) el.value.indeterminate = props.indeterminado;
};
onMounted(aplicarIndeterminado);
watch(() => props.indeterminado, aplicarIndeterminado);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2 text-md text-fg-0" :class="{ 'cursor-not-allowed opacity-60': disabled }">
        <input
            v-bind="atributos"
            ref="el"
            type="checkbox"
            class="form-checkbox h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-2 focus:ring-focus/25 focus:ring-offset-0"
            :checked="marcada()"
            :disabled="disabled"
            :aria-label="ariaLabel ?? undefined"
            @change="aoMudar"
        />
        <span v-if="rotulo || $slots.default"><slot>{{ rotulo }}</slot></span>
    </label>
</template>
