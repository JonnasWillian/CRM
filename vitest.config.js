import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

// Configuração própria (não a do Vite da aplicação) para não carregar o
// laravel-vite-plugin nos testes de componente.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        globals: true,
    },
});
