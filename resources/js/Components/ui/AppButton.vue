<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppSpinner from './AppSpinner.vue';

/**
 * Botão do app inteiro. Variantes: primary (ação principal da tela, uma por
 * tela), secondary (borda), ghost (sem fundo), danger (destrutiva).
 * `carregando` troca o ícone por um spinner, desabilita e marca aria-busy,
 * mantendo a largura. Botão só com ícone usa IconButton.
 */
const props = defineProps({
    variante: { type: String, default: 'primary' },
    tamanho: { type: String, default: 'md' },
    carregando: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    as: { type: String, default: 'button' }, // button | a | Link
    href: { type: String, default: undefined },
    bloco: { type: Boolean, default: false },
    ariaLabel: { type: String, default: undefined },
});

const tag = computed(() => (props.as === 'Link' ? Link : props.as));
const ehBotao = computed(() => props.as === 'button');

const VARIANTES = {
    primary: 'bg-primary text-primary-fg hover:bg-primary-hover active:bg-primary-active',
    secondary: 'bg-bg-1 text-fg-0 border border-line hover:bg-bg-2 hover:border-line-2',
    ghost: 'bg-transparent text-fg-1 hover:bg-bg-2 hover:text-fg-0',
    danger: 'bg-danger text-primary-fg hover:bg-danger-hover',
};

const TAMANHOS = {
    sm: 'h-8 px-3 text-sm',
    md: 'h-9 px-3.5 text-md',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded font-medium whitespace-nowrap select-none',
    'transition-colors duration-1 ease-ui disabled:opacity-60 disabled:cursor-not-allowed',
    VARIANTES[props.variante] ?? VARIANTES.primary,
    TAMANHOS[props.tamanho] ?? TAMANHOS.md,
    props.bloco ? 'w-full' : '',
]);
</script>

<template>
    <component
        :is="tag"
        :type="ehBotao ? type : undefined"
        :href="ehBotao ? undefined : href"
        :disabled="ehBotao ? disabled || carregando : undefined"
        :aria-disabled="!ehBotao && (disabled || carregando) ? 'true' : undefined"
        :aria-busy="carregando ? 'true' : undefined"
        :aria-label="ariaLabel"
        :data-variante="variante"
        :data-tamanho="tamanho"
        :class="classes"
    >
        <AppSpinner v-if="carregando" data-spinner :tamanho="tamanho === 'sm' ? 14 : 16" />
        <span v-else-if="$slots.icone" class="inline-flex shrink-0 [&>svg]:h-4 [&>svg]:w-4"><slot name="icone" /></span>
        <slot />
        <span v-if="$slots.iconeDireita" class="inline-flex shrink-0 [&>svg]:h-4 [&>svg]:w-4"><slot name="iconeDireita" /></span>
    </component>
</template>
