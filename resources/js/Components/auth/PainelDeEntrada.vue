<script setup>
/**
 * Metade comercial das telas de entrada (login e cadastro): a promessa do
 * produto e uma miniatura do dashboard desenhada em HTML — os mesmos números
 * fictícios do site, sempre rotulados como exemplo. Nada de depoimentos ou
 * estatísticas inventadas. Fundo azul profundo nos dois temas (tokens painel-*).
 *
 * `passos` só entra quando o conteúdo é mesmo uma sequência (cadastro).
 */
defineProps({
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    passos: { type: Array, default: () => [] },
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
    <div class="flex w-full flex-col justify-between gap-10">
        <div class="max-w-md">
            <h2
                class="font-display text-[28px] font-bold leading-[1.15] tracking-tight text-painel-fg xl:text-[32px]"
            >
                {{ titulo }}
            </h2>
            <p
                v-if="descricao"
                class="mt-4 text-[15px] leading-relaxed text-painel-fg-2"
            >
                {{ descricao }}
            </p>

            <ol v-if="passos.length" class="mt-7 space-y-3">
                <li
                    v-for="(passo, i) in passos"
                    :key="passo"
                    class="flex items-start gap-3 text-[15px] text-painel-fg"
                >
                    <span
                        class="num mt-px flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-painel-line text-xs font-semibold text-painel-fg-2"
                        aria-hidden="true"
                        >{{ i + 1 }}</span
                    >
                    <span>{{ passo }}</span>
                </li>
            </ol>
        </div>

        <!-- Miniatura do produto: dados fictícios, rotulados como exemplo. -->
        <figure data-miniatura class="max-w-[520px]">
            <div
                class="rounded-xl bg-bg-1 p-3 shadow-3 ring-1 ring-painel-line"
                aria-hidden="true"
            >
                <div class="mb-3 flex items-center justify-between px-1">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-fg-0"
                    >
                        Pipeline
                        <span
                            class="rounded-full bg-bg-2 px-2 py-0.5 text-xs font-medium text-fg-2"
                            >48 leads · R$ 210 mil</span
                        >
                    </div>
                    <div class="h-7 w-20 rounded bg-primary/90"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="col in COLUNAS"
                        :key="col.nome"
                        class="rounded-lg border border-line bg-bg-2/60 p-2"
                        :style="{ borderTop: `3px solid ${col.cor}` }"
                    >
                        <p
                            class="mb-2 truncate px-1 text-xs font-semibold text-fg-0"
                        >
                            {{ col.nome }}
                        </p>
                        <div
                            v-for="[nome, valor] in col.cards"
                            :key="nome"
                            class="mb-2 rounded border border-line bg-bg-1 p-2 text-xs shadow-1"
                        >
                            <p class="truncate font-medium text-fg-0">
                                {{ nome }}
                            </p>
                            <p class="num mt-0.5 text-fg-2">{{ valor }}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-ok-line bg-bg-1 p-3">
                        <p class="text-xs text-fg-2">Faturando este mês</p>
                        <p
                            class="num font-display text-lg font-semibold text-ok"
                        >
                            R$ 18.400
                        </p>
                    </div>
                    <div class="rounded-lg border border-info-line bg-bg-1 p-3">
                        <p class="text-xs text-fg-2">Em projeção</p>
                        <p
                            class="num font-display text-lg font-semibold text-info"
                        >
                            R$ 96.500
                        </p>
                    </div>
                </div>
            </div>
            <figcaption class="mt-3 text-xs text-painel-fg-2">
                Dashboard com dados de exemplo. O seu mostra o que a sua equipe
                fatura e o que ainda vai entrar.
            </figcaption>
        </figure>
    </div>
</template>
