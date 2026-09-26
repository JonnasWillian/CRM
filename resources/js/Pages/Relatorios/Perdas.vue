<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Head } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Loader2, TrendingDown, Coins } from 'lucide-vue-next';

    /**
     * "Por que perdemos".
     *
     * Forma: barras horizontais ranqueadas. Os dados são a magnitude de UMA
     * medida (quanto cada motivo pesa), não identidades distintas — por isso
     * todas as barras têm a MESMA cor. Pintar cada motivo de uma cor diferente
     * sugeriria que são tipos distintos de coisa e faria a cor seguir o rank,
     * que muda a cada filtro.
     *
     * O comprimento da barra já codifica a magnitude; variar tom por tamanho
     * seria codificar a mesma coisa duas vezes.
     *
     * Cor validada contra a superfície #13192a: banda de luminosidade, piso de
     * croma e contraste >= 3:1, todos PASS.
     */
    const COR_BARRA = '#6d5dfc';

    const dados      = ref(null);
    const carregando = ref(false);
    const erro       = ref('');
    const tipo       = ref('');
    const de         = ref('');
    const ate        = ref('');
    const hoverId    = ref(null);

    const buscar = async () => {
        carregando.value = true;
        erro.value = '';
        try {
            const params = {};
            if (de.value)   params.de   = de.value;
            if (ate.value)  params.ate  = ate.value;
            if (tipo.value) params.tipo = tipo.value;

            const res = await axios.get('/api/relatorios/perdas', { params });
            dados.value = res.data;
        } catch (e) {
            erro.value = e?.response?.status === 403
                ? 'Você não tem permissão para ver o consolidado da empresa.'
                : 'Não foi possível carregar o relatório.';
        } finally {
            carregando.value = false;
        }
    };

    // A barra é proporcional ao MAIOR motivo, não ao total: com sete motivos
    // bem distribuídos, escalar pelo total deixaria todas as barras curtas e
    // indistinguíveis.
    const maior = computed(() =>
        Math.max(1, ...(dados.value?.por_motivo?.map(m => m.total) ?? [1]))
    );

    const formatBRL = (v) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

    const limpar = () => { de.value = ''; ate.value = ''; tipo.value = ''; buscar(); };

    onMounted(buscar);
</script>

<template>
    <Head title="Por que perdemos — UserFlow" />
    <AuthenticatedLayout>
        <div class="rp-page">

            <header class="rp-topbar">
                <div>
                    <h1 class="rp-title">Por que <span class="rp-accent">perdemos</span></h1>
                    <p class="rp-sub">
                        Perdas registradas no período. Um lead reaberto depois continua contando no mês em que foi perdido.
                    </p>
                </div>
            </header>

            <!-- Filtros, numa linha só acima do gráfico -->
            <div class="rp-filtros">
                <label class="rp-campo">
                    <span>De</span>
                    <input v-model="de" type="date" class="rp-input" @change="buscar" />
                </label>
                <label class="rp-campo">
                    <span>Até</span>
                    <input v-model="ate" type="date" class="rp-input" @change="buscar" />
                </label>
                <label class="rp-campo">
                    <span>Tipo</span>
                    <select v-model="tipo" class="rp-input" @change="buscar">
                        <option value="">Leads e projetos</option>
                        <option value="lead">Só leads</option>
                        <option value="projeto">Só projetos</option>
                    </select>
                </label>
                <button class="rp-btn-ghost" @click="limpar">Limpar</button>
            </div>

            <p v-if="erro" class="rp-erro">{{ erro }}</p>

            <div v-if="carregando" class="rp-loading">
                <Loader2 :size="18" class="rp-spin" /> Carregando…
            </div>

            <template v-else-if="dados">
                <!-- Números de manchete: sem plot, sem eixo -->
                <div class="rp-tiles">
                    <div class="rp-tile">
                        <span class="rp-tile-icone"><TrendingDown :size="15" /></span>
                        <div>
                            <p class="rp-tile-valor">{{ dados.total }}</p>
                            <p class="rp-tile-label">perda{{ dados.total !== 1 ? 's' : '' }} no período</p>
                        </div>
                    </div>
                    <div class="rp-tile">
                        <span class="rp-tile-icone"><Coins :size="15" /></span>
                        <div>
                            <p class="rp-tile-valor">{{ formatBRL(dados.valor_total) }}</p>
                            <p class="rp-tile-label">em negócios perdidos</p>
                        </div>
                    </div>
                    <div class="rp-tile">
                        <div>
                            <p class="rp-tile-valor rp-tile-valor--sm">
                                {{ dados.por_tipo.lead }} <span class="rp-tile-sep">leads</span>
                                · {{ dados.por_tipo.projeto }} <span class="rp-tile-sep">projetos</span>
                            </p>
                            <p class="rp-tile-label">{{ dados.periodo.de }} até {{ dados.periodo.ate }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="!dados.por_motivo.length" class="rp-vazio">
                    Nenhuma perda registrada neste período.
                </div>

                <!-- Barras: uma série, uma cor, ordenadas do maior para o menor -->
                <section v-else class="rp-grafico">
                    <h2 class="rp-grafico-titulo">Perdas por motivo</h2>

                    <div
                        v-for="m in dados.por_motivo"
                        :key="m.motivo_perda_id"
                        class="rp-linha"
                        :class="{ 'rp-linha--hover': hoverId === m.motivo_perda_id }"
                        @mouseenter="hoverId = m.motivo_perda_id"
                        @mouseleave="hoverId = null"
                    >
                        <div class="rp-linha-nome">
                            <span>{{ m.descricao }}</span>
                            <span v-if="m.arquivado" class="rp-arquivado" title="Motivo arquivado; continua nomeando as perdas antigas">arquivado</span>
                        </div>

                        <div class="rp-trilho">
                            <div
                                class="rp-barra"
                                :style="{ width: Math.max(2, (m.total / maior) * 100) + '%', background: COR_BARRA }"
                            />
                        </div>

                        <div class="rp-linha-num">
                            <span class="rp-total">{{ m.total }}</span>
                            <span class="rp-pct">{{ m.percentual }}%</span>
                        </div>

                        <div class="rp-linha-valor">{{ formatBRL(m.valor) }}</div>

                        <!-- O valor some em telas estreitas; a dica carrega ele. -->
                        <div v-if="hoverId === m.motivo_perda_id" class="rp-dica">
                            {{ m.total }} perda{{ m.total !== 1 ? 's' : '' }} · {{ m.percentual }}% · {{ formatBRL(m.valor) }}
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .rp-page {
        --bg: #0d1117; --surface: #13192a; --border: #1e2840;
        --accent: #6d5dfc; --t1: #eaedf5; --t2: #8892ab; --t3: #4a5470;
        font-family: 'DM Sans', sans-serif;
        padding: 2rem 2.25rem 4rem;
        color: var(--t1);
        min-height: 100vh;
    }

    .rp-topbar { margin-bottom: 1.25rem; }
    .rp-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
    .rp-accent { color: var(--accent); }
    .rp-sub { font-size: 0.85rem; color: var(--t2); margin-top: 0.25rem; max-width: 60ch; }

    .rp-filtros { display: flex; gap: 0.7rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .rp-campo { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.74rem; color: var(--t2); }
    .rp-input {
        background: rgba(13,17,23,0.7); border: 1px solid var(--border); border-radius: 9px;
        padding: 0.45rem 0.65rem; color: var(--t1); font-size: 0.82rem; font-family: inherit;
        outline: none; color-scheme: dark;
    }
    .rp-input:focus { border-color: var(--accent); }
    .rp-btn-ghost {
        background: transparent; color: var(--t2); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.45rem 0.85rem; font-size: 0.8rem;
        font-family: inherit; cursor: pointer;
    }
    .rp-btn-ghost:hover { color: var(--t1); border-color: var(--accent); }

    .rp-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
    .rp-tile {
        display: flex; align-items: center; gap: 0.75rem;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 12px; padding: 1rem 1.1rem;
    }
    .rp-tile-icone {
        width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
        background: rgba(109,93,252,0.15); color: var(--accent);
        display: flex; align-items: center; justify-content: center;
    }
    .rp-tile-valor { font-family: 'Syne', sans-serif; font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
    .rp-tile-valor--sm { font-size: 1rem; }
    .rp-tile-sep { color: var(--t3); font-weight: 400; font-size: 0.8rem; }
    .rp-tile-label { font-size: 0.74rem; color: var(--t2); margin-top: 0.15rem; }

    .rp-grafico { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.2rem; }
    .rp-grafico-titulo { font-size: 0.85rem; font-weight: 500; color: var(--t2); margin-bottom: 1rem; }

    .rp-linha {
        position: relative;
        display: grid;
        grid-template-columns: minmax(120px, 1.1fr) minmax(90px, 2.4fr) 76px 100px;
        align-items: center; gap: 0.75rem;
        /* 2px de respiro entre barras adjacentes, contra a superfície. */
        padding: 0.35rem 0.4rem;
        border-radius: 8px;
    }
    .rp-linha--hover { background: rgba(109,93,252,0.07); }

    /* O nome e os números usam tokens de texto, nunca a cor da série: a cor
       identifica a marca, não o rótulo. */
    .rp-linha-nome { font-size: 0.82rem; color: var(--t1); display: flex; align-items: center; gap: 0.4rem; min-width: 0; }
    .rp-linha-nome > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rp-arquivado {
        font-size: 0.62rem; color: var(--t3); border: 1px solid var(--border);
        border-radius: 999px; padding: 1px 6px; flex-shrink: 0;
    }

    .rp-trilho { height: 10px; background: rgba(148,163,184,0.09); border-radius: 4px; overflow: hidden; }
    /* Extremidade arredondada de 4px, ancorada na linha de base (esquerda). */
    .rp-barra { height: 100%; border-radius: 0 4px 4px 0; transition: width .25s ease; }

    .rp-linha-num { display: flex; align-items: baseline; gap: 0.4rem; justify-content: flex-end; }
    .rp-total { font-size: 0.9rem; font-weight: 600; color: var(--t1); }
    .rp-pct { font-size: 0.72rem; color: var(--t2); }
    .rp-linha-valor { font-size: 0.78rem; color: var(--t2); text-align: right; }

    .rp-dica {
        position: absolute; right: 0.4rem; bottom: calc(100% - 0.2rem); z-index: 5;
        background: #0d1117; border: 1px solid var(--border); border-radius: 8px;
        padding: 0.35rem 0.6rem; font-size: 0.74rem; color: var(--t1); white-space: nowrap;
        pointer-events: none;
    }

    .rp-erro {
        font-size: 0.83rem; color: #fca5a5; background: rgba(239,68,68,0.1);
        border: 1px solid rgba(239,68,68,0.35); border-radius: 10px;
        padding: 0.7rem 0.9rem; margin-bottom: 1.25rem;
    }

    .rp-loading, .rp-vazio {
        display: flex; align-items: center; gap: 0.5rem; justify-content: center;
        color: var(--t2); font-size: 0.88rem; padding: 3rem 0;
    }
    .rp-spin { animation: rp-rot 1s linear infinite; }
    @keyframes rp-rot { to { transform: rotate(360deg); } }

    @media (max-width: 860px) {
        .rp-page { padding: 1.25rem 1rem 3rem; }
        .rp-linha { grid-template-columns: minmax(100px, 1fr) minmax(70px, 1.8fr) 70px; }
        .rp-linha-valor { display: none; }
    }
</style>
