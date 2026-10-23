<script setup>
import { computed, ref } from "vue";
import { Eye, EyeOff } from "lucide-vue-next";
import {
    BORDA_CAMPO,
    CLASSES_CAMPO,
    TAMANHO_CAMPO,
    useCampo,
} from "@/Components/ui/campo";

/**
 * Campo de senha com mostrar/ocultar. Substitui o par senha + confirmação:
 * quem vê o que digitou não precisa digitar duas vezes.
 */
const props = defineProps({
    modelValue: { type: String, default: "" },
    autocomplete: { type: String, default: "current-password" },
    placeholder: { type: String, default: null },
    id: { type: String, default: null },
    autofocus: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);
const { invalido, atributos } = useCampo(props);
const visivel = ref(false);
const rotulo = computed(() =>
    visivel.value ? "Ocultar senha" : "Mostrar senha",
);
</script>

<template>
    <div class="relative">
        <input
            v-bind="atributos"
            :type="visivel ? 'text' : 'password'"
            :value="modelValue"
            :autocomplete="autocomplete"
            :placeholder="placeholder"
            :autofocus="autofocus"
            :disabled="disabled"
            spellcheck="false"
            :class="[
                CLASSES_CAMPO,
                BORDA_CAMPO(invalido),
                TAMANHO_CAMPO.md,
                'pr-11',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r text-fg-2 hover:text-fg-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
            :aria-label="rotulo"
            :aria-pressed="visivel"
            :disabled="disabled"
            @click="visivel = !visivel"
        >
            <component
                :is="visivel ? EyeOff : Eye"
                class="h-4 w-4"
                aria-hidden="true"
            />
        </button>
    </div>
</template>
