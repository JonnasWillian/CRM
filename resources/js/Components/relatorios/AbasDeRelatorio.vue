<script setup>
import { Link } from '@inertiajs/vue3';

/** Navegação entre os relatórios (são páginas, não abas de estado): Visão geral · Por que perdemos. */
defineProps({ atual: { type: String, required: true } });
const ABAS = [
    { chave: 'relatorios.index', rotulo: 'Visão geral' },
    { chave: 'relatorios.perdas', rotulo: 'Por que perdemos' },
];
</script>

<template>
    <nav aria-label="Relatórios" class="mb-4 flex gap-1 border-b border-line">
        <Link
            v-for="a in ABAS"
            :key="a.chave"
            :href="route(a.chave)"
            :aria-current="atual === a.chave ? 'page' : undefined"
            class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors duration-1 ease-ui"
            :class="atual === a.chave ? 'border-primary text-primary' : 'border-transparent text-fg-2 hover:text-fg-0'"
        >
            {{ a.rotulo }}
        </Link>
    </nav>
</template>
