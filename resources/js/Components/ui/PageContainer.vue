<script setup>
/** Área de conteúdo de uma página: largura máxima e respiro lateral (--page-x). */
defineProps({ largura: { type: String, default: 'lg' } }); // lg 1400 · full

const LARGURAS = { lg: 'max-w-[1400px]', full: 'max-w-none' };
</script>

<template>
    <div class="mx-auto w-full px-page py-5 md:py-6" :class="LARGURAS[largura] ?? LARGURAS.lg">
        <slot />
    </div>
</template>
