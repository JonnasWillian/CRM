<script setup>
import { CircleCheck, CircleX, Info, TriangleAlert, X } from 'lucide-vue-next';
import { useToast } from '@/Composables/useToast';

/** Região viva dos toasts; montada uma vez no layout. */
const { toasts, fechar, acionar, pausar, retomar } = useToast();

const ICONES = { sucesso: CircleCheck, erro: CircleX, aviso: TriangleAlert, info: Info };
const CORES = { sucesso: 'text-ok', erro: 'text-danger', aviso: 'text-warn', info: 'text-info' };
</script>

<template>
    <div aria-live="polite" aria-relevant="additions" class="pointer-events-none fixed inset-x-3 bottom-3 z-[80] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-[380px]">
        <TransitionGroup name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                :role="t.tom === 'erro' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full items-start gap-3 rounded-lg border border-line bg-bg-1 px-3.5 py-3 text-md shadow-3"
                @mouseenter="pausar(t.id)"
                @mouseleave="retomar(t.id)"
            >
                <component :is="ICONES[t.tom] ?? Info" :size="16" class="mt-0.5 shrink-0" :class="CORES[t.tom]" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="text-fg-0">{{ t.mensagem }}</p>
                    <p v-if="t.descricao" class="mt-0.5 text-sm text-fg-2">{{ t.descricao }}</p>
                </div>
                <button v-if="t.acao" type="button" class="shrink-0 rounded px-1.5 py-0.5 text-sm font-semibold text-primary hover:bg-primary-subtle" @click="acionar(t.id)">
                    {{ t.acao.rotulo }}
                </button>
                <button type="button" aria-label="Fechar" class="-mr-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded text-fg-2 hover:bg-bg-2 hover:text-fg-0" @click="fechar(t.id)">
                    <X :size="14" aria-hidden="true" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: opacity var(--d-2) var(--e), transform var(--d-2) var(--e); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(6px); }
</style>
