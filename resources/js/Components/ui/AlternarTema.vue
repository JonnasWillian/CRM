<script setup>
import { computed } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { useTema } from '@/Composables/useTema';
import AppTooltip from './AppTooltip.vue';

/**
 * Um ícone só, nas telas sem login: o sol no tema claro, a lua no escuro, e
 * o clique alterna. Logado, o tema fica no menu da conta (SeletorDeTema,
 * com a opção de seguir o sistema).
 */
const { aplicado, escolher } = useTema();
const escuro = computed(() => aplicado.value === 'escuro');
const rotulo = computed(() => (escuro.value ? 'Usar tema claro' : 'Usar tema escuro'));
</script>

<template>
    <AppTooltip :texto="rotulo" lado="bottom">
        <button
            type="button"
            :aria-label="rotulo"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-line bg-bg-1 text-fg-1 shadow-1 transition-colors duration-1 ease-ui hover:text-primary"
            @click="escolher(escuro ? 'claro' : 'escuro')"
        >
            <component :is="escuro ? Moon : Sun" :size="18" aria-hidden="true" />
        </button>
    </AppTooltip>
</template>
