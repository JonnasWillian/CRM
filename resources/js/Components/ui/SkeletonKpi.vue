<script setup>
import AppSkeleton from './AppSkeleton.vue';
</script>

<template>
    <div aria-hidden="true" class="rounded-lg border border-line bg-bg-1 p-4 shadow-1">
        <AppSkeleton largura="45%" />
        <AppSkeleton variante="rect" largura="60%" altura="1.75rem" class="mt-3" />
        <AppSkeleton largura="35%" class="mt-3" />
    </div>
</template>
