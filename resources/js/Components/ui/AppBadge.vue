<script setup>
import { computed } from 'vue';

/**
 * Rótulo curto de estado. `tom` usa os tokens de status; `cor` (hex do
 * estágio, vindo do banco) nunca é usada crua: o texto é a cor misturada
 * com --fg-0 e o fundo é a cor misturada com --bg-1, para garantir
 * contraste qualquer que seja a cor escolhida na configuração do funil.
 */
const props = defineProps({
    tom: { type: String, default: 'neutral' }, // neutral | primary | ok | warn | danger | info
    cor: { type: String, default: null },
    ponto: { type: Boolean, default: false },
    tamanho: { type: String, default: 'sm' },
});

const HEX = /^#[0-9a-fA-F]{6}$/;

const tomEfetivo = computed(() => {
    if (props.cor) return HEX.test(props.cor) ? 'cor' : 'neutral';
    return props.tom;
});

const estilo = computed(() => (tomEfetivo.value === 'cor' ? { '--badge-cor': props.cor } : undefined));

const TONS = {
    neutral: 'bg-bg-2 text-fg-1',
    primary: 'bg-primary-subtle text-primary',
    ok: 'bg-ok-subtle text-ok',
    warn: 'bg-warn-subtle text-warn',
    danger: 'bg-danger-subtle text-danger',
    info: 'bg-info-subtle text-info',
    cor: '',
};
</script>

<template>
    <span
        :data-tom="tomEfetivo"
        :style="estilo"
        class="badge inline-flex max-w-full items-center gap-1.5 rounded-sm font-medium leading-none"
        :class="[TONS[tomEfetivo], tamanho === 'md' ? 'px-2 py-1 text-sm' : 'px-1.5 py-[3px] text-xs']"
    >
        <span v-if="ponto" class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true" />
        <span class="truncate"><slot /></span>
    </span>
</template>

<style scoped>
.badge[data-tom='cor'] {
    color: color-mix(in srgb, var(--badge-cor) 65%, var(--fg-0));
    background: color-mix(in srgb, var(--badge-cor) 14%, var(--bg-1));
}
.badge[data-tom='cor'] > .bg-current {
    background: var(--badge-cor);
}
</style>
