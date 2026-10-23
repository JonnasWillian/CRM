<script setup>
import { usePage } from '@inertiajs/vue3';

/** Casca das telas de entrada: cartão centrado, claro, sem painel de marketing. */
const nome = usePage().props.app?.nome ?? 'CRM';
defineProps({
    titulo: { type: String, default: null },
    descricao: { type: String, default: null },
});
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center bg-bg-0 px-4 py-10">
        <a href="/" class="mb-6 font-display text-2xl font-bold tracking-tight text-fg-0">{{ nome }}<span class="text-primary">.</span></a>
        <div class="w-full max-w-[420px] rounded-lg border border-line bg-bg-1 p-6 shadow-1 sm:p-8">
            <header v-if="titulo" class="mb-6">
                <h1 class="font-display text-xl font-semibold text-fg-0">{{ titulo }}</h1>
                <p v-if="descricao" class="mt-1 text-sm text-fg-2">{{ descricao }}</p>
            </header>
            <slot />
        </div>
        <footer v-if="$slots.rodape" class="mt-6 text-sm text-fg-2"><slot name="rodape" /></footer>
    </div>
</template>
