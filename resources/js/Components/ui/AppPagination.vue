<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

/**
 * Paginação numerada com elipse: 1 … n-1 n n+1 … último, mais "26–50 de
 * 1.243" e o seletor de itens por página (teto do servidor: 100).
 */
const props = defineProps({
    meta: { type: Object, required: true }, // { current_page, last_page, per_page, total, from, to }
    opcoesPorPagina: { type: Array, default: () => [25, 50, 100] },
    carregando: { type: Boolean, default: false },
});

const emit = defineEmits(['update:pagina', 'update:porPagina']);

const atual = computed(() => props.meta.current_page ?? 1);
const ultima = computed(() => Math.max(1, props.meta.last_page ?? 1));

const paginas = computed(() => {
    const janela = new Set([1, ultima.value, atual.value - 1, atual.value, atual.value + 1].filter((p) => p >= 1 && p <= ultima.value));
    const ordenadas = [...janela].sort((a, b) => a - b);
    const saida = [];
    let anterior = 0;
    for (const p of ordenadas) {
        if (p - anterior > 1) saida.push('…');
        saida.push(p);
        anterior = p;
    }
    return saida;
});

const intervalo = computed(() => {
    const total = Number(props.meta.total ?? 0).toLocaleString('pt-BR');
    if (!props.meta.total) return '0 resultados';
    return `${props.meta.from ?? 0}–${props.meta.to ?? 0} de ${total}`;
});

const ir = (p) => {
    if (p < 1 || p > ultima.value || p === atual.value || props.carregando) return;
    emit('update:pagina', p);
};
</script>

<template>
    <nav aria-label="Paginação" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="num text-sm text-fg-2">{{ intervalo }}</p>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-fg-2">
                <span class="hidden sm:inline">Por página</span>
                <select
                    :value="meta.per_page"
                    class="form-select h-9 rounded border-line bg-bg-1 py-0 pl-2 pr-8 text-sm text-fg-0 focus:border-focus focus:ring-2 focus:ring-focus/25"
                    @change="emit('update:porPagina', Number($event.target.value))"
                >
                    <option v-for="n in opcoesPorPagina" :key="n" :value="n">{{ n }}</option>
                </select>
            </label>
            <div class="flex items-center gap-1">
                <button type="button" aria-label="Página anterior" :disabled="atual <= 1 || carregando" class="pg-botao" @click="ir(atual - 1)">
                    <ChevronLeft :size="16" aria-hidden="true" />
                </button>
                <template v-for="(p, i) in paginas" :key="`${p}-${i}`">
                    <span v-if="p === '…'" data-pagina="…" class="px-1 text-sm text-fg-2" aria-hidden="true">…</span>
                    <button
                        v-else
                        type="button"
                        :data-pagina="p"
                        :aria-current="p === atual ? 'page' : undefined"
                        :aria-label="`Página ${p}`"
                        :disabled="carregando"
                        class="pg-botao num min-w-9 px-2"
                        :class="p === atual ? 'bg-primary text-primary-fg hover:bg-primary' : ''"
                        @click="ir(p)"
                    >
                        {{ p }}
                    </button>
                </template>
                <button type="button" aria-label="Próxima página" :disabled="atual >= ultima || carregando" class="pg-botao" @click="ir(atual + 1)">
                    <ChevronRight :size="16" aria-hidden="true" />
                </button>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.pg-botao {
    display: inline-flex; align-items: center; justify-content: center;
    height: 36px; min-width: 36px; border-radius: var(--r-2);
    border: 1px solid var(--line); background: var(--bg-1); color: var(--fg-1);
    font-size: var(--fs-sm); font-weight: 500;
    transition: background var(--d-1) var(--e), color var(--d-1) var(--e);
}
.pg-botao:hover:not(:disabled) { background: var(--bg-2); color: var(--fg-0); }
.pg-botao:disabled { opacity: .5; cursor: not-allowed; }
</style>
