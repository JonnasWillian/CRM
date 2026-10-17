<script setup>
import { useCampo } from './campo';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    rotulo: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { atributos } = useCampo(props);
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-2 text-md text-fg-0" :class="{ 'cursor-not-allowed opacity-60': disabled }">
        <button
            v-bind="atributos"
            type="button"
            role="switch"
            :aria-checked="modelValue ? 'true' : 'false'"
            :disabled="disabled"
            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-1 ease-ui"
            :class="modelValue ? 'bg-primary' : 'bg-line-2'"
            @click="emit('update:modelValue', !modelValue)"
        >
            <span class="inline-block h-4 w-4 rounded-full bg-bg-1 shadow-1 transition-transform duration-1 ease-ui" :class="modelValue ? 'translate-x-[18px]' : 'translate-x-0.5'" aria-hidden="true" />
        </button>
        <span v-if="rotulo || $slots.default"><slot>{{ rotulo }}</slot></span>
    </label>
</template>
