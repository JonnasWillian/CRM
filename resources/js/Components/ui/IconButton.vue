<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppSpinner from './AppSpinner.vue';
import AppTooltip from './AppTooltip.vue';

/**
 * Botão só de ícone: o `rotulo` é obrigatório porque vira o aria-label e a
 * dica — um ícone sem nome é um botão sem nome. Sempre visível (nada de
 * opacity 0 até o hover), com alvo mínimo de 32/36/40px.
 */
// O invólucro do tooltip tem mais de uma raiz (span + Teleport), então os
// atributos e ouvintes (@click, aria-expanded…) vão explicitamente ao botão.
defineOptions({ inheritAttrs: false });

const props = defineProps({
    icone: { type: [Object, Function], required: true },
    rotulo: { type: String, required: true },
    tamanho: { type: String, default: 'md' }, // sm 32 · md 36 · lg 40
    variante: { type: String, default: 'ghost' }, // ghost | secondary | primary | danger
    carregando: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    as: { type: String, default: 'button' },
    href: { type: String, default: undefined },
    ladoTooltip: { type: String, default: 'top' },
    type: { type: String, default: 'button' },
});

const tag = computed(() => (props.as === 'Link' ? Link : props.as));
const ehBotao = computed(() => props.as === 'button');

const TAMANHOS = { sm: 'h-8 w-8', md: 'h-9 w-9', lg: 'h-10 w-10' };
const ICONES = { sm: 14, md: 16, lg: 18 };
const VARIANTES = {
    ghost: 'text-fg-1 hover:bg-bg-2 hover:text-fg-0',
    secondary: 'border border-line bg-bg-1 text-fg-1 hover:bg-bg-2 hover:text-fg-0',
    primary: 'bg-primary text-primary-fg hover:bg-primary-hover',
    danger: 'text-fg-1 hover:bg-danger-subtle hover:text-danger',
};

const classes = computed(() => [
    'inline-flex shrink-0 items-center justify-center rounded transition-colors duration-1 ease-ui',
    'disabled:opacity-60 disabled:cursor-not-allowed',
    TAMANHOS[props.tamanho] ?? TAMANHOS.md,
    VARIANTES[props.variante] ?? VARIANTES.ghost,
]);
</script>

<template>
    <AppTooltip :texto="rotulo" :lado="ladoTooltip">
        <component
            :is="tag"
            v-bind="$attrs"
            :type="ehBotao ? type : undefined"
            :href="ehBotao ? undefined : href"
            :disabled="ehBotao ? disabled || carregando : undefined"
            :aria-busy="carregando ? 'true' : undefined"
            :aria-label="rotulo"
            :data-variante="variante"
            :class="classes"
        >
            <AppSpinner v-if="carregando" :tamanho="ICONES[tamanho] ?? 16" />
            <component :is="icone" v-else :size="ICONES[tamanho] ?? 16" aria-hidden="true" />
        </component>
    </AppTooltip>
</template>
