<script setup>
import { computed } from 'vue';

/**
 * Abas com semântica ARIA (tablist/tab), setas para navegar, e, com
 * `sincronizarUrl="aba"`, a aba ativa refletida em ?aba= (sem recarregar) —
 * o que permite linkar /leads/1?aba=tarefas.
 */
const props = defineProps({
    modelValue: { type: String, required: true },
    abas: { type: Array, required: true }, // [{ chave, rotulo, icone?, contagem?, disabled? }]
    sincronizarUrl: { type: String, default: null },
    tamanho: { type: String, default: 'md' }, // sm | md
});

const emit = defineEmits(['update:modelValue']);

const habilitadas = computed(() => props.abas.filter((a) => !a.disabled));

function selecionar(chave) {
    if (chave === props.modelValue) return;
    emit('update:modelValue', chave);
    if (props.sincronizarUrl && typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set(props.sincronizarUrl, chave);
        window.history.replaceState(window.history.state, '', url);
    }
}

function aoTeclar(e, chave) {
    const lista = habilitadas.value;
    const i = lista.findIndex((a) => a.chave === chave);
    let alvo = null;
    if (e.key === 'ArrowRight') alvo = lista[(i + 1) % lista.length];
    else if (e.key === 'ArrowLeft') alvo = lista[(i - 1 + lista.length) % lista.length];
    else if (e.key === 'Home') alvo = lista[0];
    else if (e.key === 'End') alvo = lista[lista.length - 1];
    if (!alvo) return;
    e.preventDefault();
    selecionar(alvo.chave);
    e.currentTarget.parentElement?.querySelector(`[data-aba="${alvo.chave}"]`)?.focus();
}
</script>

<template>
    <div role="tablist" class="flex gap-1 overflow-x-auto border-b border-line" :class="tamanho === 'sm' ? 'text-sm' : 'text-md'">
        <button
            v-for="aba in abas"
            :id="`aba-${aba.chave}`"
            :key="aba.chave"
            type="button"
            role="tab"
            :data-aba="aba.chave"
            :aria-selected="aba.chave === modelValue ? 'true' : 'false'"
            :aria-controls="`painel-${aba.chave}`"
            :tabindex="aba.chave === modelValue ? 0 : -1"
            :disabled="aba.disabled"
            class="-mb-px inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 font-medium transition-colors duration-1 ease-ui disabled:opacity-50"
            :class="[
                tamanho === 'sm' ? 'px-2.5 py-2' : 'px-3 py-2.5',
                aba.chave === modelValue ? 'border-primary text-primary' : 'border-transparent text-fg-1 hover:border-line-2 hover:text-fg-0',
            ]"
            @click="selecionar(aba.chave)"
            @keydown="aoTeclar($event, aba.chave)"
        >
            <component :is="aba.icone" v-if="aba.icone" :size="15" aria-hidden="true" />
            {{ aba.rotulo }}
            <span v-if="aba.contagem !== undefined && aba.contagem !== null" class="num rounded-sm px-1.5 py-0.5 text-xs" :class="aba.chave === modelValue ? 'bg-primary-subtle text-primary' : 'bg-bg-2 text-fg-2'">{{ aba.contagem }}</span>
        </button>
    </div>
</template>
