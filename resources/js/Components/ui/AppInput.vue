<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { vMaska } from 'maska/vue';
import { BORDA_CAMPO, CLASSES_CAMPO, TAMANHO_CAMPO, useCampo } from './campo';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: null },
    maxlength: { type: Number, default: null },
    limite: { type: String, default: null }, // chave em props.limites (nome, email, descricao…)
    mostrarContador: { type: Boolean, default: false },
    mascara: { type: String, default: null }, // data-maska
    inputmode: { type: String, default: null },
    tamanho: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    id: { type: String, default: null },
    name: { type: String, default: null },
    autocomplete: { type: String, default: null },
    autofocus: { type: Boolean, default: false },
    min: { type: [String, Number], default: null },
    max: { type: [String, Number], default: null },
    step: { type: [String, Number], default: null },
});

const emit = defineEmits(['update:modelValue']);
const { invalido, atributos } = useCampo(props);

const limites = (() => {
    try {
        return usePage().props?.limites ?? {};
    } catch {
        return {};
    }
})();

const maxlength = computed(() => props.maxlength ?? (props.limite ? limites[props.limite] : null) ?? null);
const tamanhoTexto = computed(() => String(props.modelValue ?? '').length);
</script>

<template>
    <div class="relative" :class="{ 'flex items-center': $slots.prefixo || $slots.sufixo }">
        <span v-if="$slots.prefixo" class="pointer-events-none absolute left-3 text-fg-2 [&>svg]:h-4 [&>svg]:w-4"><slot name="prefixo" /></span>
        <input
            v-bind="atributos"
            v-maska
            :data-maska="mascara || undefined"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :maxlength="maxlength ?? undefined"
            :inputmode="inputmode"
            :disabled="disabled"
            :readonly="readonly"
            :name="name"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :min="min ?? undefined"
            :max="max ?? undefined"
            :step="step ?? undefined"
            :class="[CLASSES_CAMPO, BORDA_CAMPO(invalido), TAMANHO_CAMPO[tamanho] ?? TAMANHO_CAMPO.md, $slots.prefixo ? 'pl-9' : '', $slots.sufixo ? 'pr-9' : '']"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <span v-if="$slots.sufixo" class="pointer-events-none absolute right-3 text-fg-2 [&>svg]:h-4 [&>svg]:w-4"><slot name="sufixo" /></span>
        <p v-if="mostrarContador && maxlength" class="num mt-1 text-right text-xs text-fg-2" aria-live="polite">{{ tamanhoTexto }}/{{ maxlength }}</p>
    </div>
</template>
