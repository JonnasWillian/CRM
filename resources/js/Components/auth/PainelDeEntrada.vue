<script setup>
import { computed } from "vue";

/**
 * Metade comercial das telas de entrada (login e cadastro): a promessa do
 * produto e uma miniatura do dashboard desenhada em HTML — os mesmos números
 * fictícios do site, sempre rotulados como exemplo. Nada de depoimentos ou
 * estatísticas inventadas. Fica sobre o fundo da tela de entrada, nos dois temas.
 * O trecho depois da última vírgula do título ganha destaque na cor da marca.
 *
 * `passos` só entra quando o conteúdo é mesmo uma sequência (cadastro).
 */
const props = defineProps({
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    passos: { type: Array, default: () => [] },
});

const partes = computed(() => {
    const i = props.titulo.lastIndexOf(",");
    if (i < 0 || i === props.titulo.length - 1) return { antes: props.titulo, destaque: null };
    return { antes: props.titulo.slice(0, i + 1), destaque: props.titulo.slice(i + 1).trim() };
});

const COLUNAS = [
    {
        nome: "Em captação",
        cor: "var(--cat-1)",
        cards: [
            ["Ana Souza", "R$ 8.000"],
            ["Loja Horizonte", "R$ 3.200"],
        ],
    },
    {
        nome: "Proposta enviada",
        cor: "var(--cat-2)",
        cards: [
            ["Clínica Vitale", "R$ 12.000"],
            ["Nexa Studio", "R$ 4.800"],
        ],
    },
    {
        nome: "Ganho",
        cor: "var(--ok)",
        cards: [["Agência Prisma", "R$ 2.400/mês"]],
    },
];
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-[560px] flex-col justify-center gap-9 2xl:max-w-[640px] 2xl:gap-11"
    >
        <div>
            <h2
                class="font-display text-[32px] font-light leading-[1.15] tracking-tight text-fg-0 xl:text-[40px] 2xl:text-[46px]"
            >
                {{ partes.antes
                }}<template v-if="partes.destaque"
                    >{{ " "
                    }}<span data-destaque class="whitespace-nowrap font-bold text-primary">{{
                        partes.destaque
                    }}</span></template
                >
            </h2>
            <span
                class="mt-5 block h-1.5 w-11 rounded-full bg-primary"
                aria-hidden="true"
            ></span>
            <p
                v-if="descricao"
                class="mt-5 max-w-[58ch] text-[15px] leading-relaxed text-fg-1"
            >
                {{ descricao }}
            </p>

            <ol v-if="passos.length" class="mt-7 space-y-3">
                <li
                    v-for="(passo, i) in passos"
                    :key="passo"
                    class="flex items-start gap-3 text-[15px] text-fg-0"
                >
                    <span
                        class="num mt-px flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-subtle text-xs font-semibold text-primary"
                        aria-hidden="true"
                        >{{ i + 1 }}</span
                    >
                    <span>{{ passo }}</span>
                </li>
            </ol>
        </div>

        <!-- Miniatura do produto: dados fictícios, rotulados como exemplo. -->
        <figure data-miniatura class="w-full">
            <div class="rounded-xl bg-bg-1 p-4 shadow-2" aria-hidden="true">
                <div class="mb-3 flex items-center gap-2 px-1 text-md font-semibold text-fg-0">
                    Pipeline
                    <span
                        class="rounded-full bg-bg-2 px-2.5 py-0.5 text-xs font-medium text-fg-1"
                        >48 leads · R$ 210 mil</span
                    >
                </div>
                <div class="grid grid-cols-3 gap-2.5">
                    <div
                        v-for="col in COLUNAS"
                        :key="col.nome"
                        class="rounded-2xl bg-bg-2 p-2.5"
                    >
                        <p
                            class="mb-2.5 flex items-center gap-1.5 truncate px-1 text-xs font-semibold text-fg-0"
                        >
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :style="{ background: col.cor }"
                            ></span>
                            {{ col.nome }}
                        </p>
                        <div
                            v-for="[nome, valor] in col.cards"
                            :key="nome"
                            class="mb-2 rounded bg-bg-1 px-3 py-2 text-xs shadow-1"
                        >
                            <p class="truncate font-medium text-fg-0">
                                {{ nome }}
                            </p>
                            <p class="num mt-0.5 text-fg-2">{{ valor }}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-2.5">
                    <div class="rounded-2xl bg-bg-2 px-3.5 py-3">
                        <p class="text-xs text-fg-2">Faturando este mês</p>
                        <p class="num font-display text-lg font-semibold text-ok">
                            R$ 18.400
                        </p>
                    </div>
                    <div class="rounded-2xl bg-bg-2 px-3.5 py-3">
                        <p class="text-xs text-fg-2">Em projeção</p>
                        <p
                            class="num font-display text-lg font-semibold text-primary"
                        >
                            R$ 96.500
                        </p>
                    </div>
                </div>
            </div>
            <figcaption class="mt-3 text-xs text-fg-2">
                Dashboard com dados de exemplo. O seu mostra o que a sua equipe
                fatura e o que ainda vai entrar.
            </figcaption>
        </figure>
    </div>
</template>
