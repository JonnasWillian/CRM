<script setup>
    import { ref, onMounted } from 'vue';
    import { Head } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Plus, Pencil, Archive, ArchiveRestore, Star, ChevronUp, ChevronDown, X, Save, Loader2 } from 'lucide-vue-next';

    const funis     = ref([]);
    const isLoading = ref(false);
    const erro      = ref('');
    const salvando  = ref(false);

    // Espelha Estagio::TIPOS. É o tipo, não o nome, que as métricas leem —
    // por isso ele aparece na tela como escolha explícita e não como detalhe.
    const TIPOS = [
        { valor: 'aberto',  rotulo: 'Aberto',  ajuda: 'Negócio em andamento. Conta como lead ativo.' },
        { valor: 'ganho',   rotulo: 'Ganho',   ajuda: 'Negócio fechado com sucesso.' },
        { valor: 'perdido', rotulo: 'Perdido', ajuda: 'Negócio encerrado sem fechar.' },
    ];

    const PALETA = ['#60a5fa', '#f59e0b', '#34d399', '#ef4444', '#8b5cf6', '#ec4899', '#22d3ee', '#a3e635'];

    const formFunil    = ref(null);   // { id|null, nome, descricao }
    const formEstagio  = ref(null);   // { id|null, funil_id, descricao, tipo, cor }

    const buscar = async () => {
        isLoading.value = true;
        erro.value = '';
        try {
            const res = await axios.get('/api/funis');
            funis.value = res.data;
        } catch {
            erro.value = 'Não foi possível carregar os funis.';
        } finally {
            isLoading.value = false;
        }
    };

    // O backend recusa operações que quebrariam uma invariante (arquivar o
    // funil padrão, remover o último estágio aberto) com 422 e uma mensagem
    // pronta para leitura. Mostrá-la crua é melhor que traduzir aqui e correr o
    // risco de as duas versões divergirem.
    const tratarErro = (e, padrao) => {
        erro.value = e?.response?.data?.error
            ?? Object.values(e?.response?.data?.erros ?? {}).flat()[0]
            ?? padrao;
    };

    const abrirNovoFunil   = () => { formFunil.value = { id: null, nome: '', descricao: '' }; erro.value = ''; };
    const abrirEditarFunil = (f) => { formFunil.value = { id: f.id, nome: f.nome, descricao: f.descricao ?? '' }; erro.value = ''; };
    const fecharFunil      = () => { formFunil.value = null; };

    const salvarFunil = async () => {
        salvando.value = true;
        erro.value = '';
        try {
            const payload = { nome: formFunil.value.nome, descricao: formFunil.value.descricao || null };
            if (formFunil.value.id) await axios.put(`/api/funis/${formFunil.value.id}`, payload);
            else                     await axios.post('/api/funis', payload);
            fecharFunil();
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível salvar o funil.');
        } finally {
            salvando.value = false;
        }
    };

    const definirPadrao = async (f) => {
        erro.value = '';
        try {
            await axios.patch(`/api/funis/${f.id}/padrao`);
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível definir o funil padrão.');
        }
    };

    const arquivarFunil = async (f) => {
        if (!confirm(`Arquivar o funil "${f.nome}"? Os estágios dele são arquivados junto.`)) return;
        erro.value = '';
        try {
            await axios.delete(`/api/funis/${f.id}`);
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível arquivar o funil.');
        }
    };

    const moverFunil = async (indice, direcao) => {
        const destino = indice + direcao;
        if (destino < 0 || destino >= funis.value.length) return;

        const ids = funis.value.map(f => f.id);
        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];

        erro.value = '';
        try {
            await axios.patch('/api/funis/reordenar', { ids });
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível reordenar os funis.');
        }
    };

    const abrirNovoEstagio = (f) => {
        formEstagio.value = { id: null, funil_id: f.id, descricao: '', tipo: 'aberto', cor: PALETA[0] };
        erro.value = '';
    };

    const abrirEditarEstagio = (f, e) => {
        formEstagio.value = { id: e.id, funil_id: f.id, descricao: e.descricao, tipo: e.tipo, cor: e.cor ?? PALETA[0] };
        erro.value = '';
    };

    const fecharEstagio = () => { formEstagio.value = null; };

    const salvarEstagio = async () => {
        salvando.value = true;
        erro.value = '';
        const { id, funil_id, descricao, tipo, cor } = formEstagio.value;
        try {
            if (id) await axios.put(`/api/estagios/${id}`, { descricao, tipo, cor });
            else    await axios.post(`/api/funis/${funil_id}/estagios`, { descricao, tipo, cor });
            fecharEstagio();
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível salvar o estágio.');
        } finally {
            salvando.value = false;
        }
    };

    const arquivarEstagio = async (e) => {
        if (!confirm(`Arquivar o estágio "${e.descricao}"?`)) return;
        erro.value = '';
        try {
            await axios.delete(`/api/estagios/${e.id}`);
            await buscar();
        } catch (err) {
            tratarErro(err, 'Não foi possível arquivar o estágio.');
        }
    };

    const restaurarEstagio = async (e) => {
        erro.value = '';
        try {
            await axios.patch(`/api/estagios/${e.id}/restaurar`);
            await buscar();
        } catch (err) {
            tratarErro(err, 'Não foi possível restaurar o estágio.');
        }
    };

    const moverEstagio = async (funil, indice, direcao) => {
        const destino = indice + direcao;
        if (destino < 0 || destino >= funil.estagios.length) return;

        const ids = funil.estagios.map(e => e.id);
        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];

        erro.value = '';
        try {
            await axios.patch(`/api/funis/${funil.id}/estagios/reordenar`, { ids });
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível reordenar os estágios.');
        }
    };

    const rotuloTipo = (tipo) => TIPOS.find(t => t.valor === tipo)?.rotulo ?? tipo;

    onMounted(buscar);
</script>

<template>
    <Head title="Funis — UserFlow" />
    <AuthenticatedLayout>
        <div class="cf-page">

            <div class="cf-topbar">
                <div>
                    <h1 class="cf-title">Funis <span class="cf-accent">e estágios</span></h1>
                    <p class="cf-sub">Cada funil tem seus próprios estágios. Um lead vive em um funil por vez.</p>
                </div>
                <button class="cf-btn-primary" @click="abrirNovoFunil">
                    <Plus :size="14" /> Novo funil
                </button>
            </div>

            <Transition name="cf-fade">
                <div v-if="erro" class="cf-erro">
                    <span>{{ erro }}</span>
                    <button class="cf-erro-close" @click="erro = ''"><X :size="13" /></button>
                </div>
            </Transition>

            <!-- Formulário de funil -->
            <Transition name="cf-fade">
                <div v-if="formFunil" class="cf-form-card">
                    <div class="cf-form-title">{{ formFunil.id ? 'Editar funil' : 'Novo funil' }}</div>
                    <div class="cf-form-row">
                        <input v-model="formFunil.nome" class="cf-input" placeholder="Nome do funil (ex: Pós-venda)" @keydown.enter="salvarFunil" />
                        <input v-model="formFunil.descricao" class="cf-input" placeholder="Descrição (opcional)" @keydown.enter="salvarFunil" />
                    </div>
                    <p v-if="!formFunil.id" class="cf-hint">
                        O funil nasce com um estágio aberto, para já poder receber leads. Você renomeia depois.
                    </p>
                    <div class="cf-form-actions">
                        <button class="cf-btn-ghost" @click="fecharFunil"><X :size="12" /> Cancelar</button>
                        <button class="cf-btn-primary" :disabled="salvando || !formFunil.nome" @click="salvarFunil">
                            <Save :size="12" /> {{ salvando ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </Transition>

            <div v-if="isLoading" class="cf-loading">
                <Loader2 :size="18" class="cf-spin" /> Carregando funis…
            </div>

            <div v-else-if="!funis.length" class="cf-vazio">
                Nenhum funil ainda. Crie o primeiro para começar.
            </div>

            <div v-else class="cf-lista">
                <section v-for="(funil, iF) in funis" :key="funil.id" class="cf-funil">

                    <header class="cf-funil-head">
                        <div class="cf-funil-id">
                            <h2 class="cf-funil-nome">{{ funil.nome }}</h2>
                            <span v-if="funil.is_default" class="cf-badge cf-badge--padrao" title="Leads criados sem funil explícito caem aqui">Padrão</span>
                            <span class="cf-badge">{{ funil.total_leads }} lead{{ funil.total_leads !== 1 ? 's' : '' }}</span>
                        </div>

                        <div class="cf-funil-acoes">
                            <button class="cf-icon" :disabled="iF === 0" title="Subir" @click="moverFunil(iF, -1)"><ChevronUp :size="14" /></button>
                            <button class="cf-icon" :disabled="iF === funis.length - 1" title="Descer" @click="moverFunil(iF, 1)"><ChevronDown :size="14" /></button>
                            <button v-if="!funil.is_default" class="cf-icon" title="Tornar padrão" @click="definirPadrao(funil)"><Star :size="14" /></button>
                            <button class="cf-icon" title="Editar funil" @click="abrirEditarFunil(funil)"><Pencil :size="14" /></button>
                            <button class="cf-icon cf-icon--perigo" title="Arquivar funil" @click="arquivarFunil(funil)"><Archive :size="14" /></button>
                        </div>
                    </header>

                    <p v-if="funil.descricao" class="cf-funil-desc">{{ funil.descricao }}</p>

                    <ul class="cf-estagios">
                        <li
                            v-for="(estagio, iE) in funil.estagios"
                            :key="estagio.id"
                            class="cf-estagio"
                            :class="{ 'cf-estagio--arquivado': estagio.arquivada }"
                        >
                            <span class="cf-cor" :style="{ background: estagio.cor || '#4a5470' }" />
                            <span class="cf-estagio-nome">{{ estagio.descricao }}</span>
                            <span
                                v-if="estagio.arquivada"
                                class="cf-tipo cf-tipo--arquivado"
                                title="Arquivado. Continua aparecendo no Kanban, como somente-saída, enquanto ainda tiver leads."
                            >Arquivado</span>
                            <span class="cf-tipo" :class="`cf-tipo--${estagio.tipo}`">{{ rotuloTipo(estagio.tipo) }}</span>

                            <div class="cf-estagio-acoes">
                                <template v-if="estagio.arquivada">
                                    <button class="cf-icon" title="Restaurar estágio" @click="restaurarEstagio(estagio)"><ArchiveRestore :size="13" /></button>
                                </template>
                                <template v-else>
                                    <button class="cf-icon" :disabled="iE === 0" title="Subir" @click="moverEstagio(funil, iE, -1)"><ChevronUp :size="13" /></button>
                                    <button class="cf-icon" :disabled="iE === funil.estagios.length - 1" title="Descer" @click="moverEstagio(funil, iE, 1)"><ChevronDown :size="13" /></button>
                                    <button class="cf-icon" title="Editar estágio" @click="abrirEditarEstagio(funil, estagio)"><Pencil :size="13" /></button>
                                    <button class="cf-icon cf-icon--perigo" title="Arquivar estágio" @click="arquivarEstagio(estagio)"><Archive :size="13" /></button>
                                </template>
                            </div>
                        </li>
                    </ul>

                    <!-- Formulário de estágio, ancorado no funil a que pertence -->
                    <div v-if="formEstagio && formEstagio.funil_id === funil.id" class="cf-form-card cf-form-card--inline">
                        <div class="cf-form-title">{{ formEstagio.id ? 'Editar estágio' : 'Novo estágio' }}</div>

                        <input v-model="formEstagio.descricao" class="cf-input" placeholder="Nome do estágio" @keydown.enter="salvarEstagio" />

                        <div class="cf-tipos">
                            <label v-for="t in TIPOS" :key="t.valor" class="cf-tipo-opt" :class="{ 'cf-tipo-opt--on': formEstagio.tipo === t.valor }" :title="t.ajuda">
                                <input type="radio" :value="t.valor" v-model="formEstagio.tipo" />
                                <span>{{ t.rotulo }}</span>
                            </label>
                        </div>
                        <p class="cf-hint">{{ TIPOS.find(t => t.valor === formEstagio.tipo)?.ajuda }}</p>

                        <div class="cf-cores">
                            <button
                                v-for="c in PALETA"
                                :key="c"
                                class="cf-cor-opt"
                                :class="{ 'cf-cor-opt--on': formEstagio.cor === c }"
                                :style="{ background: c }"
                                @click="formEstagio.cor = c"
                            />
                        </div>

                        <div class="cf-form-actions">
                            <button class="cf-btn-ghost" @click="fecharEstagio"><X :size="12" /> Cancelar</button>
                            <button class="cf-btn-primary" :disabled="salvando || !formEstagio.descricao" @click="salvarEstagio">
                                <Save :size="12" /> {{ salvando ? 'Salvando…' : 'Salvar' }}
                            </button>
                        </div>
                    </div>

                    <button v-else class="cf-add-estagio" @click="abrirNovoEstagio(funil)">
                        <Plus :size="13" /> Adicionar estágio
                    </button>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .cf-page {
        --bg: #0d1117; --surface: #13192a; --border: #1e2840;
        --accent: #6d5dfc; --t1: #eaedf5; --t2: #8892ab; --t3: #4a5470;
        font-family: 'DM Sans', sans-serif;
        padding: 2rem 2.25rem 4rem;
        color: var(--t1);
        min-height: 100vh;
    }

    .cf-topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .cf-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
    .cf-accent { color: var(--accent); }
    .cf-sub { font-size: 0.85rem; color: var(--t2); margin-top: 0.25rem; }

    .cf-erro {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.35);
        color: #fca5a5; border-radius: 10px; padding: 0.7rem 0.9rem;
        font-size: 0.83rem; margin-bottom: 1.25rem;
    }
    .cf-erro-close { background: none; border: none; color: inherit; cursor: pointer; display: flex; }

    .cf-lista { display: flex; flex-direction: column; gap: 1rem; }

    .cf-funil { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 1.1rem 1.2rem; }
    .cf-funil-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .cf-funil-id { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
    .cf-funil-nome { font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 700; }
    .cf-funil-desc { font-size: 0.8rem; color: var(--t2); margin-top: 0.35rem; }
    .cf-funil-acoes, .cf-estagio-acoes { display: flex; align-items: center; gap: 0.25rem; }

    .cf-badge {
        font-size: 0.68rem; font-weight: 500; padding: 2px 8px; border-radius: 999px;
        background: rgba(136,146,171,0.12); color: var(--t2); border: 1px solid var(--border);
    }
    .cf-badge--padrao { background: rgba(109,93,252,0.15); color: var(--accent); border-color: rgba(109,93,252,0.35); }

    .cf-estagios { list-style: none; margin: 0.9rem 0 0; display: flex; flex-direction: column; gap: 0.35rem; }
    .cf-estagio {
        display: flex; align-items: center; gap: 0.6rem;
        background: rgba(13,17,23,0.55); border: 1px solid var(--border);
        border-radius: 10px; padding: 0.55rem 0.7rem;
    }
    .cf-estagio-nome { flex: 1; font-size: 0.85rem; }
    .cf-cor { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }

    .cf-tipo { font-size: 0.66rem; font-weight: 500; padding: 2px 7px; border-radius: 999px; }
    .cf-tipo--aberto  { color: #60a5fa; background: rgba(96,165,250,0.14); }
    .cf-tipo--ganho   { color: #34d399; background: rgba(52,211,153,0.14); }
    .cf-tipo--perdido { color: #ef4444; background: rgba(239,68,68,0.14); }
    .cf-tipo--arquivado { color: #8892ab; background: rgba(136,146,171,0.14); }

    .cf-estagio--arquivado { opacity: 0.55; border-style: dashed; }

    .cf-icon {
        width: 26px; height: 26px; border-radius: 7px; border: 1px solid var(--border);
        background: transparent; color: var(--t2); cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: color .15s, border-color .15s;
    }
    .cf-icon:hover:not(:disabled) { color: var(--t1); border-color: var(--accent); }
    .cf-icon:disabled { opacity: 0.3; cursor: not-allowed; }
    .cf-icon--perigo:hover:not(:disabled) { color: #f06292; border-color: rgba(240,98,146,0.45); }

    .cf-add-estagio {
        margin-top: 0.6rem; display: inline-flex; align-items: center; gap: 0.35rem;
        background: transparent; border: 1px dashed var(--border); color: var(--t2);
        border-radius: 9px; padding: 0.45rem 0.8rem; font-size: 0.78rem; cursor: pointer;
    }
    .cf-add-estagio:hover { color: var(--accent); border-color: var(--accent); }

    .cf-form-card { background: var(--surface); border: 1px solid var(--accent); border-radius: 12px; padding: 1rem 1.1rem; margin-bottom: 1.25rem; }
    .cf-form-card--inline { margin: 0.75rem 0 0; }
    .cf-form-title { font-size: 0.8rem; font-weight: 500; color: var(--t2); margin-bottom: 0.7rem; }
    .cf-form-row { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .cf-form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.85rem; }

    .cf-input {
        flex: 1; min-width: 200px; background: rgba(13,17,23,0.7); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.55rem 0.75rem; color: var(--t1); font-size: 0.85rem;
        font-family: inherit; outline: none;
    }
    .cf-input:focus { border-color: var(--accent); }

    .cf-hint { font-size: 0.74rem; color: var(--t3); margin-top: 0.5rem; }

    .cf-tipos { display: flex; gap: 0.4rem; margin-top: 0.7rem; flex-wrap: wrap; }
    .cf-tipo-opt {
        display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;
        border: 1px solid var(--border); border-radius: 999px; padding: 0.3rem 0.75rem;
        font-size: 0.78rem; color: var(--t2);
    }
    .cf-tipo-opt input { display: none; }
    .cf-tipo-opt--on { border-color: var(--accent); color: var(--accent); background: rgba(109,93,252,0.12); }

    .cf-cores { display: flex; gap: 0.35rem; margin-top: 0.7rem; flex-wrap: wrap; }
    .cf-cor-opt { width: 22px; height: 22px; border-radius: 6px; border: 2px solid transparent; cursor: pointer; }
    .cf-cor-opt--on { border-color: var(--t1); }

    .cf-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--accent); color: #fff; border: none; border-radius: 9px;
        padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }
    .cf-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .cf-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: transparent; color: var(--t2); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }

    .cf-loading, .cf-vazio {
        display: flex; align-items: center; gap: 0.5rem; justify-content: center;
        color: var(--t2); font-size: 0.88rem; padding: 3rem 0;
    }
    .cf-spin { animation: cf-rot 1s linear infinite; }
    @keyframes cf-rot { to { transform: rotate(360deg); } }

    .cf-fade-enter-active, .cf-fade-leave-active { transition: opacity .18s; }
    .cf-fade-enter-from, .cf-fade-leave-to { opacity: 0; }

    @media (max-width: 768px) {
        .cf-page { padding: 1.25rem 1rem 3rem; }
    }
</style>
