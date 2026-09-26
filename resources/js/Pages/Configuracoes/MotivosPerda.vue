<script setup>
    import { ref, onMounted } from 'vue';
    import { Head } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Plus, Pencil, Archive, ArchiveRestore, ChevronUp, ChevronDown, X, Save, Loader2 } from 'lucide-vue-next';

    const motivos    = ref([]);
    const isLoading  = ref(false);
    const erro       = ref('');
    const salvando   = ref(false);
    const form       = ref(null);   // { id|null, descricao }

    const buscar = async () => {
        isLoading.value = true;
        erro.value = '';
        try {
            // incluir_arquivados: esta tela precisa mostrar os aposentados para
            // poder restaurá-los; o seletor de quem registra uma perda, não.
            const res = await axios.get('/api/motivos-perda', { params: { incluir_arquivados: 1 } });
            motivos.value = res.data;
        } catch {
            erro.value = 'Não foi possível carregar os motivos.';
        } finally {
            isLoading.value = false;
        }
    };

    // O backend recusa o que quebraria a invariante (arquivar o último motivo)
    // com 422 e a mensagem pronta. Mostrá-la crua evita duas versões do mesmo
    // texto divergindo.
    const tratarErro = (e, padrao) => {
        erro.value = e?.response?.data?.error
            ?? Object.values(e?.response?.data?.erros ?? {}).flat()[0]
            ?? padrao;
    };

    const abrirNovo   = ()  => { form.value = { id: null, descricao: '' }; erro.value = ''; };
    const abrirEditar = (m) => { form.value = { id: m.id, descricao: m.descricao }; erro.value = ''; };
    const fechar      = ()  => { form.value = null; };

    const salvar = async () => {
        salvando.value = true;
        erro.value = '';
        try {
            const payload = { descricao: form.value.descricao };
            if (form.value.id) await axios.put(`/api/motivos-perda/${form.value.id}`, payload);
            else                await axios.post('/api/motivos-perda', payload);
            fechar();
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível salvar o motivo.');
        } finally {
            salvando.value = false;
        }
    };

    const arquivar = async (m) => {
        if (m.total_perdas > 0
            && !confirm(`"${m.descricao}" já foi usado em ${m.total_perdas} perda(s). Arquivar mantém o histórico intacto e só tira o motivo do seletor. Continuar?`)) return;
        erro.value = '';
        try {
            await axios.delete(`/api/motivos-perda/${m.id}`);
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível arquivar o motivo.');
        }
    };

    const restaurar = async (m) => {
        erro.value = '';
        try {
            await axios.patch(`/api/motivos-perda/${m.id}/restaurar`);
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível restaurar o motivo.');
        }
    };

    const mover = async (indice, direcao) => {
        const destino = indice + direcao;
        if (destino < 0 || destino >= motivos.value.length) return;

        const ids = motivos.value.map(m => m.id);
        [ids[indice], ids[destino]] = [ids[destino], ids[indice]];

        erro.value = '';
        try {
            await axios.patch('/api/motivos-perda/reordenar', { ids });
            await buscar();
        } catch (e) {
            tratarErro(e, 'Não foi possível reordenar.');
        }
    };

    onMounted(buscar);
</script>

<template>
    <Head title="Motivos de perda — UserFlow" />
    <AuthenticatedLayout>
        <div class="mt-page">

            <div class="mt-topbar">
                <div>
                    <h1 class="mt-title">Motivos <span class="mt-accent">de perda</span></h1>
                    <p class="mt-sub">
                        Esta lista é o que o time escolhe ao perder um lead ou projeto, e é o que o
                        relatório "por que perdemos" agrupa.
                    </p>
                </div>
                <button class="mt-btn-primary" @click="abrirNovo"><Plus :size="14" /> Novo motivo</button>
            </div>

            <Transition name="mt-fade">
                <div v-if="erro" class="mt-erro">
                    <span>{{ erro }}</span>
                    <button class="mt-erro-close" @click="erro = ''"><X :size="13" /></button>
                </div>
            </Transition>

            <Transition name="mt-fade">
                <div v-if="form" class="mt-form">
                    <div class="mt-form-title">{{ form.id ? 'Editar motivo' : 'Novo motivo' }}</div>
                    <input
                        v-model="form.descricao"
                        class="mt-input"
                        placeholder="Ex: Escolheu concorrente"
                        @keydown.enter="salvar"
                    />
                    <p v-if="form.id" class="mt-hint">
                        Renomear vale também para as perdas que já citam este motivo — o relatório passa a
                        exibir o nome novo no histórico inteiro.
                    </p>
                    <div class="mt-form-actions">
                        <button class="mt-btn-ghost" @click="fechar"><X :size="12" /> Cancelar</button>
                        <button class="mt-btn-primary" :disabled="salvando || !form.descricao" @click="salvar">
                            <Save :size="12" /> {{ salvando ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </Transition>

            <div v-if="isLoading" class="mt-loading"><Loader2 :size="18" class="mt-spin" /> Carregando…</div>

            <ul v-else class="mt-lista">
                <li
                    v-for="(m, i) in motivos"
                    :key="m.id"
                    class="mt-item"
                    :class="{ 'mt-item--arquivado': m.arquivado }"
                >
                    <span class="mt-nome">{{ m.descricao }}</span>

                    <span v-if="m.arquivado" class="mt-badge" title="Fora do seletor; continua nomeando as perdas antigas">arquivado</span>
                    <span v-if="m.total_perdas" class="mt-badge mt-badge--uso">
                        {{ m.total_perdas }} perda{{ m.total_perdas !== 1 ? 's' : '' }}
                    </span>

                    <div class="mt-acoes">
                        <template v-if="m.arquivado">
                            <button class="mt-icon" title="Restaurar" @click="restaurar(m)"><ArchiveRestore :size="13" /></button>
                        </template>
                        <template v-else>
                            <button class="mt-icon" :disabled="i === 0" title="Subir" @click="mover(i, -1)"><ChevronUp :size="13" /></button>
                            <button class="mt-icon" :disabled="i === motivos.length - 1" title="Descer" @click="mover(i, 1)"><ChevronDown :size="13" /></button>
                            <button class="mt-icon" title="Editar" @click="abrirEditar(m)"><Pencil :size="13" /></button>
                            <button class="mt-icon mt-icon--perigo" title="Arquivar" @click="arquivar(m)"><Archive :size="13" /></button>
                        </template>
                    </div>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .mt-page {
        --surface: #13192a; --border: #1e2840; --accent: #6d5dfc;
        --t1: #eaedf5; --t2: #8892ab; --t3: #4a5470;
        font-family: 'DM Sans', sans-serif;
        padding: 2rem 2.25rem 4rem; color: var(--t1); min-height: 100vh;
    }

    .mt-topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .mt-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
    .mt-accent { color: var(--accent); }
    .mt-sub { font-size: 0.85rem; color: var(--t2); margin-top: 0.25rem; max-width: 62ch; }

    .mt-erro {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.35);
        color: #fca5a5; border-radius: 10px; padding: 0.7rem 0.9rem;
        font-size: 0.83rem; margin-bottom: 1.25rem;
    }
    .mt-erro-close { background: none; border: none; color: inherit; cursor: pointer; display: flex; }

    .mt-form { background: var(--surface); border: 1px solid var(--accent); border-radius: 12px; padding: 1rem 1.1rem; margin-bottom: 1.25rem; }
    .mt-form-title { font-size: 0.8rem; font-weight: 500; color: var(--t2); margin-bottom: 0.7rem; }
    .mt-form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.85rem; }
    .mt-hint { font-size: 0.74rem; color: var(--t3); margin-top: 0.5rem; }

    .mt-input {
        width: 100%; background: rgba(13,17,23,0.7); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.55rem 0.75rem; color: var(--t1);
        font-size: 0.85rem; font-family: inherit; outline: none;
    }
    .mt-input:focus { border-color: var(--accent); }

    .mt-lista { list-style: none; display: flex; flex-direction: column; gap: 0.4rem; }
    .mt-item {
        display: flex; align-items: center; gap: 0.6rem;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 10px; padding: 0.65rem 0.8rem;
    }
    .mt-item--arquivado { opacity: 0.55; border-style: dashed; }
    .mt-nome { flex: 1; font-size: 0.87rem; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .mt-badge {
        font-size: 0.66rem; padding: 2px 7px; border-radius: 999px;
        background: rgba(136,146,171,0.12); color: var(--t2); border: 1px solid var(--border); flex-shrink: 0;
    }
    .mt-badge--uso { background: rgba(109,93,252,0.12); color: var(--accent); border-color: rgba(109,93,252,0.3); }

    .mt-acoes { display: flex; gap: 0.25rem; flex-shrink: 0; }
    .mt-icon {
        width: 26px; height: 26px; border-radius: 7px; border: 1px solid var(--border);
        background: transparent; color: var(--t2); cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: color .15s, border-color .15s;
    }
    .mt-icon:hover:not(:disabled) { color: var(--t1); border-color: var(--accent); }
    .mt-icon:disabled { opacity: 0.3; cursor: not-allowed; }
    .mt-icon--perigo:hover:not(:disabled) { color: #f06292; border-color: rgba(240,98,146,0.45); }

    .mt-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--accent); color: #fff; border: none; border-radius: 9px;
        padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }
    .mt-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .mt-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: transparent; color: var(--t2); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.5rem 0.9rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }

    .mt-loading { display: flex; align-items: center; gap: 0.5rem; justify-content: center; color: var(--t2); font-size: 0.88rem; padding: 3rem 0; }
    .mt-spin { animation: mt-rot 1s linear infinite; }
    @keyframes mt-rot { to { transform: rotate(360deg); } }

    .mt-fade-enter-active, .mt-fade-leave-active { transition: opacity .18s; }
    .mt-fade-enter-from, .mt-fade-leave-to { opacity: 0; }

    @media (max-width: 768px) { .mt-page { padding: 1.25rem 1rem 3rem; } }
</style>
