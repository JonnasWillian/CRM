<script setup>
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useTema } from '@/Composables/useTema';

/** Claro · Escuro · Sistema. A escolha vale para este navegador. */
defineProps({ compacto: { type: Boolean, default: false } });
const { preferencia, escolher } = useTema();
const OPCOES = [
    { valor: 'claro', rotulo: 'Claro', icone: Sun },
    { valor: 'escuro', rotulo: 'Escuro', icone: Moon },
    { valor: 'sistema', rotulo: 'Sistema', icone: Monitor },
];
</script>

<template>
    <div role="radiogroup" aria-label="Tema" class="flex w-full rounded border border-line bg-bg-1 p-0.5">
        <button
            v-for="o in OPCOES"
            :key="o.valor"
            type="button"
            role="radio"
            :aria-checked="preferencia === o.valor ? 'true' : 'false'"
            :aria-label="compacto ? `Tema ${o.rotulo.toLowerCase()}` : undefined"
            :title="compacto ? o.rotulo : undefined"
            class="flex h-8 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-sm px-2 text-sm font-medium transition-colors duration-1 ease-ui"
            :class="preferencia === o.valor ? 'bg-primary-subtle text-primary' : 'text-fg-1 hover:text-fg-0'"
            @click.stop="escolher(o.valor)"
        >
            <component :is="o.icone" :size="14" aria-hidden="true" />
            <span v-if="!compacto" class="truncate">{{ o.rotulo }}</span>
        </button>
    </div>
</template>
