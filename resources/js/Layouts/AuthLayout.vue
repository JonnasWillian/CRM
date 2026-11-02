<script setup>
import { usePage } from "@inertiajs/vue3";
import AlternarTema from "@/Components/ui/AlternarTema.vue";

/**
 * Telas sem sessão, sobre o fundo em degradê suave (--fundo-entrada).
 * Dois arranjos:
 *  - cartão centrado (recuperar senha, confirmar, verificar e-mail, convite);
 *  - duas colunas quando a página passa o slot `painel` (login e cadastro):
 *    o painel comercial (PainelDeEntrada) à esquerda e o formulário num
 *    cartão flutuante à direita, só a partir de `lg`. Abaixo disso, a marca
 *    e o lema ficam no topo e o cartão ocupa a largura.
 * O botão de tema (sol ou lua) fica no canto superior direito: sem login,
 * é o único lugar para trocar o tema.
 */
const pagina = usePage();
const nome = pagina.props.app?.nome ?? "CRM";
// O botão de tema é só de quem não está logado; logado, o tema fica no menu da conta.
const semLogin = !pagina.props.auth?.user;

defineProps({
    titulo: { type: String, default: null },
    descricao: { type: String, default: null },
    lema: { type: String, default: null }, // frase curta da faixa no celular
    largo: { type: Boolean, default: false }, // cartão centrado de 640px (questionário)
});
</script>

<template>
    <!-- Duas colunas -->
    <div
        v-if="$slots.painel"
        class="min-h-dvh"
        style="background: var(--fundo-entrada)"
    >
        <div
            class="mx-auto flex min-h-dvh w-full max-w-[1280px] flex-col px-4 py-5 sm:px-8 lg:px-10 lg:py-8"
        >
            <header class="flex items-center justify-between gap-3">
                <a
                    href="/"
                    class="font-display text-[22px] font-bold tracking-tight text-fg-0"
                    >{{ nome }}<span class="text-primary">.</span></a
                >
                <AlternarTema v-if="semLogin" />
            </header>
            <p data-faixa-mobile class="mt-1 text-sm text-fg-2 lg:hidden">
                {{ lema }}
            </p>

            <div
                class="flex flex-1 flex-col py-8 lg:grid lg:grid-cols-[minmax(0,1fr)_440px] lg:items-center lg:gap-16 xl:gap-24"
            >
                <aside
                    data-painel
                    class="hidden lg:flex lg:items-stretch lg:justify-center"
                >
                    <slot name="painel" />
                </aside>

                <main class="flex justify-center">
                    <div
                        class="w-full max-w-[440px] rounded-xl bg-bg-1 px-6 py-8 shadow-3 sm:px-10 sm:py-10"
                    >
                        <header v-if="titulo" class="mb-7">
                            <h1
                                class="font-display text-[26px] font-semibold leading-tight tracking-tight text-fg-0"
                            >
                                {{ titulo }}
                            </h1>
                            <p
                                v-if="descricao"
                                class="mt-2 text-md leading-relaxed text-fg-2"
                            >
                                {{ descricao }}
                            </p>
                        </header>
                        <slot />
                        <footer
                            v-if="$slots.rodape"
                            class="mt-7 text-center text-md text-fg-2 [&_a]:inline-block"
                        >
                            <slot name="rodape" />
                        </footer>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Cartão centrado -->
    <div
        v-else
        class="relative flex min-h-dvh flex-col items-center justify-center px-4 py-10"
        style="background: var(--fundo-entrada)"
    >
        <div v-if="semLogin" class="absolute right-4 top-4 sm:right-8 sm:top-6">
            <AlternarTema />
        </div>
        <a
            href="/"
            class="mb-6 font-display text-[22px] font-bold tracking-tight text-fg-0"
            >{{ nome }}<span class="text-primary">.</span></a
        >
        <div
            class="w-full rounded-xl bg-bg-1 px-6 py-8 shadow-3 sm:px-10 sm:py-10"
            :class="largo ? 'max-w-[640px]' : 'max-w-[440px]'"
        >
            <header v-if="titulo" class="mb-6">
                <h1
                    class="font-display text-[22px] font-semibold tracking-tight text-fg-0"
                >
                    {{ titulo }}
                </h1>
                <p v-if="descricao" class="mt-1.5 text-md text-fg-2">
                    {{ descricao }}
                </p>
            </header>
            <slot />
        </div>
        <footer v-if="$slots.rodape" class="mt-6 text-md text-fg-2">
            <slot name="rodape" />
        </footer>
    </div>
</template>
