<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useContador } from '@/Composables/useContador';
import SkeletonKpi from './SkeletonKpi.vue';

/**
 * Indicador do dashboard: rótulo pequeno, número grande, sub-linha. Com
 * `contar`, o número anima até o novo valor quando muda (nunca na primeira
 * carga — ver useContador). `href` leva para a lista filtrada que explica o
 * número.
 */
const props = defineProps({
    rotulo: { type: String, required: true },
    valor: { type: [Number, String], default: null },
    sub: { type: String, default: null },
    icone: { type: [Object, Function], default: null },
    tom: { type: String, default: 'neutral' }, // neutral | primary | ok | warn | danger
    carregando: { type: Boolean, default: false },
    contar: { type: Boolean, default: false },
    formatar: { type: Function, default: null },
    href: { type: String, default: null },
});

const numerico = computed(() => (typeof props.valor === 'number' ? props.valor : null));
const contado = useContador(() => numerico.value ?? 0);

const exibido = computed(() => {
    if (props.valor === null || props.valor === undefined) return '—';
    if (typeof props.valor === 'string') return props.valor;
    const n = props.contar ? contado.value : props.valor;
    return props.formatar ? props.formatar(n) : Math.round(n).toLocaleString('pt-BR');
});

const TONS = {
    neutral: 'text-fg-2',
    primary: 'text-primary',
    ok: 'text-ok',
    warn: 'text-warn',
    danger: 'text-danger',
};
</script>

<template>
    <SkeletonKpi v-if="carregando" />
    <component
        :is="href ? Link : 'div'"
        v-else
        :href="href ?? undefined"
        class="block rounded-lg border border-line bg-bg-1 p-4 shadow-1 transition-colors duration-1 ease-ui"
        :class="href ? 'hover:border-line-2 hover:bg-bg-2/40' : ''"
    >
        <div class="flex items-center justify-between gap-2">
            <p class="truncate text-sm font-medium text-fg-2">{{ rotulo }}</p>
            <component :is="icone" v-if="icone" :size="16" class="shrink-0" :class="TONS[tom] ?? TONS.neutral" aria-hidden="true" />
        </div>
        <p class="num mt-2 truncate font-display text-3xl font-semibold leading-none text-fg-0">{{ exibido }}</p>
        <p v-if="sub" class="mt-2 truncate text-sm text-fg-2">{{ sub }}</p>
    </component>
</template>
