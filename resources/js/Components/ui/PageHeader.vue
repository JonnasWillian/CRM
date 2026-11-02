<script setup>
defineProps({
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: null },
});
</script>

<template>
    <header class="mb-5 md:mb-6">
        <nav v-if="$slots.migalhas" aria-label="Você está em" class="mb-2 flex items-center gap-1.5 text-sm text-fg-2">
            <slot name="migalhas" />
        </nav>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="truncate font-display text-2xl font-semibold tracking-tight text-fg-0">{{ titulo }}</h1>
                <span class="mt-2 block h-1 w-9 rounded-full bg-primary" aria-hidden="true" />
                <p v-if="subtitulo" class="mt-2 text-md text-fg-2">{{ subtitulo }}</p>
                <slot name="abaixo" />
            </div>
            <div v-if="$slots.acoes" class="flex shrink-0 flex-wrap items-center gap-2"><slot name="acoes" /></div>
        </div>
    </header>
</template>
