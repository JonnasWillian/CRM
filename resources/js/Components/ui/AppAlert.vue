<script setup>
import { computed } from 'vue';
import { CircleCheck, CircleX, Info, TriangleAlert, X } from 'lucide-vue-next';

/**
 * Mensagem fixa na página (erro de carga, aviso de regra, status). Erro e
 * aviso são anunciados (role=alert); informação e sucesso são status.
 */
const props = defineProps({
    tom: { type: String, default: 'info' }, // info | ok | warn | danger
    titulo: { type: String, default: null },
    fechavel: { type: Boolean, default: false },
});

defineEmits(['fechar']);

const ICONES = { info: Info, ok: CircleCheck, warn: TriangleAlert, danger: CircleX };
const TONS = {
    info: 'border-info-line bg-info-subtle text-info',
    ok: 'border-ok-line bg-ok-subtle text-ok',
    warn: 'border-warn-line bg-warn-subtle text-warn',
    danger: 'border-danger-line bg-danger-subtle text-danger',
};

const role = computed(() => (props.tom === 'danger' || props.tom === 'warn' ? 'alert' : 'status'));
</script>

<template>
    <div :role="role" :data-tom="tom" class="flex items-start gap-3 rounded-lg border px-3.5 py-3 text-md" :class="TONS[tom] ?? TONS.info">
        <component :is="ICONES[tom] ?? Info" :size="16" class="mt-0.5 shrink-0" aria-hidden="true" />
        <div class="min-w-0 flex-1 text-fg-0">
            <p v-if="titulo" class="font-semibold">{{ titulo }}</p>
            <div :class="{ 'mt-0.5 text-fg-1': titulo }"><slot /></div>
            <div v-if="$slots.acao" class="mt-2"><slot name="acao" /></div>
        </div>
        <button
            v-if="fechavel"
            type="button"
            aria-label="Fechar"
            class="-m-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-fg-2 hover:bg-bg-1/60 hover:text-fg-0"
            @click="$emit('fechar')"
        >
            <X :size="14" aria-hidden="true" />
        </button>
    </div>
</template>
