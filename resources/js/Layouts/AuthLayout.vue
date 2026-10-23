<script setup>
import { usePage } from "@inertiajs/vue3";

/**
 * Telas sem sessão. Dois arranjos:
 *  - cartão centrado (recuperar senha, confirmar, verificar e-mail, convite);
 *  - duas colunas quando a página passa o slot `painel` (login e cadastro):
 *    metade comercial (PainelDeEntrada) à esquerda e formulário à direita,
 *    só a partir de `lg`. Abaixo disso o painel vira uma faixa da marca.
 */
const nome = usePage().props.app?.nome ?? "CRM";

defineProps({
    titulo: { type: String, default: null },
    descricao: { type: String, default: null },
    lema: { type: String, default: null }, // frase curta da faixa no celular
});
</script>

<template>
    <!-- Duas colunas -->
    <div
        v-if="$slots.painel"
        class="flex min-h-dvh flex-col bg-bg-1 lg:grid lg:grid-cols-[minmax(0,6fr)_minmax(0,5fr)] xl:grid-cols-2"
    >
        <div
            data-faixa-mobile
            class="flex items-center justify-between gap-3 bg-painel-bg px-4 py-3 text-painel-fg lg:hidden"
        >
            <a href="/" class="font-display text-lg font-bold tracking-tight"
                >{{ nome }}<span class="text-painel-fg-2">.</span></a
            >
            <p v-if="lema" class="truncate text-sm text-painel-fg-2">
                {{ lema }}
            </p>
        </div>

        <aside
            data-painel
            class="hidden bg-painel-bg px-10 py-12 text-painel-fg lg:flex lg:min-h-dvh lg:items-stretch lg:justify-center xl:px-16"
        >
            <slot name="painel" />
        </aside>

        <main
            class="flex flex-1 flex-col px-4 py-8 sm:px-10 sm:py-12 lg:min-h-dvh"
        >
            <div
                class="mx-auto flex w-full max-w-[400px] flex-1 flex-col 2xl:max-w-[440px]"
            >
                <a
                    href="/"
                    class="hidden self-start font-display text-2xl font-bold tracking-tight text-fg-0 lg:inline-block"
                    >{{ nome }}<span class="text-primary">.</span></a
                >
                <div class="flex flex-1 flex-col justify-center py-8">
                    <header v-if="titulo" class="mb-7">
                        <h1
                            class="font-display text-[26px] font-bold leading-tight tracking-tight text-fg-0"
                        >
                            {{ titulo }}
                        </h1>
                        <p
                            v-if="descricao"
                            class="mt-2 text-md leading-relaxed text-fg-1"
                        >
                            {{ descricao }}
                        </p>
                    </header>
                    <slot />
                    <footer v-if="$slots.rodape" class="mt-8 text-md text-fg-2">
                        <slot name="rodape" />
                    </footer>
                </div>
            </div>
        </main>
    </div>

    <!-- Cartão centrado -->
    <div
        v-else
        class="flex min-h-dvh flex-col items-center justify-center bg-bg-0 px-4 py-10"
    >
        <a
            href="/"
            class="mb-6 font-display text-2xl font-bold tracking-tight text-fg-0"
            >{{ nome }}<span class="text-primary">.</span></a
        >
        <div
            class="w-full max-w-[420px] rounded-lg border border-line bg-bg-1 p-6 shadow-1 sm:p-8"
        >
            <header v-if="titulo" class="mb-6">
                <h1 class="font-display text-xl font-semibold text-fg-0">
                    {{ titulo }}
                </h1>
                <p v-if="descricao" class="mt-1 text-sm text-fg-2">
                    {{ descricao }}
                </p>
            </header>
            <slot />
        </div>
        <footer v-if="$slots.rodape" class="mt-6 text-sm text-fg-2">
            <slot name="rodape" />
        </footer>
    </div>
</template>
