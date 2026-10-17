<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { BORDA_CAMPO, CLASSES_CAMPO, useCampo } from './campo';

const props = defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    placeholder: { type: String, default: null },
    maxlength: { type: Number, default: null },
    limite: { type: String, default: null },
    mostrarContador: { type: Boolean, default: false },
    autoAjustar: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    name: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { invalido, atributos } = useCampo(props);
const el = ref(null);

const limites = (() => {
    try {
        return usePage().props?.limites ?? {};
    } catch {
        return {};
    }
})();

const maxlength = computed(() => props.maxlength ?? (props.limite ? limites[props.limite] : null) ?? null);

function ajustar() {
    if (!props.autoAjustar || !el.value) return;
    el.value.style.height = 'auto';
    el.value.style.height = `${el.value.scrollHeight}px`;
}

onMounted(ajustar);
watch(() => props.modelValue, ajustar);
</script>

<template>
    <div>
        <textarea
            v-bind="atributos"
            ref="el"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :maxlength="maxlength ?? undefined"
            :disabled="disabled"
            :name="name"
            class="resize-y px-3 py-2 text-md leading-relaxed"
            :class="[CLASSES_CAMPO, BORDA_CAMPO(invalido)]"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <p v-if="mostrarContador && maxlength" class="num mt-1 text-right text-xs text-fg-2" aria-live="polite">{{ String(modelValue ?? '').length }}/{{ maxlength }}</p>
    </div>
</template>
