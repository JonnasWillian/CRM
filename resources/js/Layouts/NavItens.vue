<script setup>
import { Link } from '@inertiajs/vue3';
import AppTooltip from '@/Components/ui/AppTooltip.vue';

/** Lista de links da navegação; usada na barra lateral e na gaveta. */
defineProps({
    grupos: { type: Array, required: true },
    colapsada: { type: Boolean, default: false },
});

const emit = defineEmits(['navegar']);

const ativa = (item) => {
    if (typeof route !== 'function') return false;
    const atual = route();
    return atual.current(item.rota) || (item.ativaEm ?? []).some((padrao) => atual.current(padrao));
};
</script>

<template>
    <template v-for="(g, gi) in grupos" :key="gi">
        <p v-if="g.titulo && !colapsada" class="px-3 pb-1.5 pt-5 text-sm font-medium text-fg-2">{{ g.titulo }}</p>
        <div v-else-if="g.titulo" class="mx-2 my-2 h-px bg-line" aria-hidden="true" />
        <template v-for="item in g.itens" :key="item.rota">
            <component :is="colapsada ? AppTooltip : 'span'" v-bind="colapsada ? { texto: item.rotulo, lado: 'right' } : {}" class="flex">
                <Link
                    :href="route(item.rota)"
                    class="relative flex w-full items-center gap-3 rounded px-3 py-2.5 text-md transition-[background-color,color,box-shadow] duration-1 ease-ui"
                    :class="[
                        ativa(item) ? 'bg-bg-1 font-medium text-primary shadow-2' : 'text-fg-1 hover:bg-bg-1/70 hover:text-fg-0',
                        colapsada ? 'justify-center' : '',
                    ]"
                    :aria-current="ativa(item) ? 'page' : undefined"
                    :data-tour="`nav-${item.rota}`"
                    :aria-label="colapsada ? item.rotulo : undefined"
                    @click="emit('navegar')"
                >
                    <component :is="item.icone" :size="18" class="shrink-0" aria-hidden="true" />
                    <span v-if="!colapsada" class="truncate">{{ item.rotulo }}</span>
                </Link>
            </component>
        </template>
    </template>
</template>
