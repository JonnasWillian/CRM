<script setup>
defineProps({ tamanho: { type: Number, default: 16 } });
</script>

<template>
    <svg
        :width="tamanho"
        :height="tamanho"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        class="animate-spin shrink-0"
        aria-hidden="true"
    >
        <circle cx="12" cy="12" r="9" class="opacity-25" />
        <path d="M21 12a9 9 0 0 0-9-9" />
    </svg>
</template>
