<script setup>
    import { ref, onMounted, computed } from 'vue';
    import { usePage, Head } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { PlusCircle, X, Save, Edit, Trash2, LayoutTemplate, CalendarDays } from 'lucide-vue-next';
    import Swal from 'sweetalert2';

    const user = computed(() => usePage().props.auth.user);

    const padroes   = ref([]);
    const isLoading = ref(false);
    const showForm  = ref(false);
    const form      = ref({ titulo: '', anotacao: '', prazo_dias: 1 });
    const editingId = ref(null);
    const editForm  = ref({ titulo: '', anotacao: '', prazo_dias: 1 });

    const buscarPadroes = async () => {
        isLoading.value = true;
        try {
            const res = await axios.get(`/api/tarefa-padroes?user_id=${user.value.id}`);
            padroes.value = res.data;
        } catch {
            padroes.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    const criarPadrao = async () => {
        if (!form.value.titulo.trim()) return;
        try {
            await axios.post('/api/tarefa-padroes', { ...form.value, user_id: user.value.id });
            form.value  = { titulo: '', anotacao: '', prazo_dias: 1 };
            showForm.value = false;
            await buscarPadroes();
        } catch { /* silencioso */ }
    };

    const iniciarEdicao = (p) => {
        editingId.value = p.id;
        editForm.value  = { titulo: p.titulo, anotacao: p.anotacao || '', prazo_dias: p.prazo_dias };
    };

    const cancelarEdicao = () => { editingId.value = null; };

    const salvarEdicao = async () => {
        if (!editForm.value.titulo.trim()) return;
        try {
            await axios.put(`/api/tarefa-padroes/${editingId.value}`, editForm.value);
            editingId.value = null;
            await buscarPadroes();
        } catch { /* silencioso */ }
    };

    const excluirPadrao = async (id) => {
        const result = await Swal.fire({
            title: 'Excluir modelo?',
            text: 'Este modelo será removido permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            background: '#13192a',
            color: '#eaedf5',
            confirmButtonColor: '#f06292',
            cancelButtonColor: '#1e2840',
        });
        if (result.isConfirmed) {
            padroes.value = padroes.value.filter(p => p.id !== id);
            try { await axios.delete(`/api/tarefa-padroes/${id}`); }
            catch { await buscarPadroes(); }
        }
    };

    const prazoLabel = (dias) => {
        if (dias === 0) return 'No mesmo dia';
        if (dias === 1) return '1 dia após';
        return `${dias} dias após`;
    };

    onMounted(buscarPadroes);
</script>

<template>
    <Head title="Modelos de Tarefa" />
    <AuthenticatedLayout>
        <div class="mt-page">

            <!-- Cabeçalho -->
            <div class="mt-header">
                <div class="mt-header-left">
                    <div class="mt-icon-wrap">
                        <LayoutTemplate :size="18" />
                    </div>
                    <div>
                        <h1 class="mt-title">Modelos de Tarefa</h1>
                        <p class="mt-sub">Crie templates reutilizáveis para agilizar o cadastro de tarefas nos leads.</p>
                    </div>
                </div>
                <button v-if="!showForm" @click="showForm = true" class="mt-btn-add">
                    <PlusCircle :size="15" /> Novo modelo
                </button>
                <button v-else @click="showForm = false; form = { titulo:'', anotacao:'', prazo_dias:1 }" class="mt-btn-ghost">
                    <X :size="15" /> Cancelar
                </button>
            </div>

            <!-- Formulário de criação -->
            <Transition name="mt-slide">
                <div v-if="showForm" class="mt-form-card">
                    <h3 class="mt-form-title">Novo modelo</h3>
                    <div class="mt-form-row">
                        <input
                            v-model="form.titulo"
                            type="text"
                            placeholder="Título do modelo..."
                            class="mt-input mt-input--grow"
                            autofocus
                        />
                        <div class="mt-prazo-wrap">
                            <CalendarDays :size="14" class="mt-prazo-icon" />
                            <input
                                v-model.number="form.prazo_dias"
                                type="number"
                                min="0"
                                class="mt-input mt-input--num"
                            />
                            <span class="mt-prazo-label">dias</span>
                        </div>
                    </div>
                    <textarea
                        v-model="form.anotacao"
                        placeholder="Descrição / anotação (opcional)..."
                        rows="2"
                        class="mt-textarea"
                    />
                    <div class="mt-form-actions">
                        <button @click="showForm = false; form = { titulo:'', anotacao:'', prazo_dias:1 }" class="mt-btn-ghost mt-btn-sm">
                            <X :size="12" /> Cancelar
                        </button>
                        <button @click="criarPadrao" class="mt-btn-primary mt-btn-sm" :disabled="!form.titulo.trim()">
                            <Save :size="12" /> Salvar modelo
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- Loading -->
            <div v-if="isLoading" class="mt-empty">
                <div class="mt-spinner" />
                <span>Carregando modelos...</span>
            </div>

            <!-- Vazio -->
            <div v-else-if="!padroes.length && !showForm" class="mt-empty">
                <LayoutTemplate :size="32" />
                <p class="mt-empty-title">Nenhum modelo cadastrado</p>
                <p class="mt-empty-sub">Crie um modelo para agilizar o cadastro de tarefas nos leads.</p>
            </div>

            <!-- Lista -->
            <div v-else class="mt-list">
                <div
                    v-for="(padrao, i) in padroes"
                    :key="padrao.id"
                    class="mt-item"
                    :style="{ animationDelay: `${i * 0.04}s` }"
                >
                    <!-- Modo visualização -->
                    <template v-if="editingId !== padrao.id">
                        <div class="mt-item-dot" />
                        <div class="mt-item-body">
                            <p class="mt-item-titulo">{{ padrao.titulo }}</p>
                            <p v-if="padrao.anotacao" class="mt-item-nota">{{ padrao.anotacao }}</p>
                        </div>
                        <span class="mt-item-prazo">
                            <CalendarDays :size="11" /> {{ prazoLabel(padrao.prazo_dias) }}
                        </span>
                        <div class="mt-item-actions">
                            <button @click="iniciarEdicao(padrao)" class="mt-icon-btn mt-icon-btn--edit" title="Editar"><Edit :size="13" /></button>
                            <button @click="excluirPadrao(padrao.id)" class="mt-icon-btn mt-icon-btn--del" title="Excluir"><Trash2 :size="13" /></button>
                        </div>
                    </template>

                    <!-- Modo edição inline -->
                    <template v-else>
                        <div class="mt-edit-wrap">
                            <div class="mt-form-row">
                                <input v-model="editForm.titulo" type="text" class="mt-input mt-input--grow" />
                                <div class="mt-prazo-wrap">
                                    <CalendarDays :size="14" class="mt-prazo-icon" />
                                    <input v-model.number="editForm.prazo_dias" type="number" min="0" class="mt-input mt-input--num" />
                                    <span class="mt-prazo-label">dias</span>
                                </div>
                            </div>
                            <textarea v-model="editForm.anotacao" rows="2" class="mt-textarea" placeholder="Descrição (opcional)..." />
                            <div class="mt-form-actions">
                                <button @click="cancelarEdicao" class="mt-btn-ghost mt-btn-sm"><X :size="12" /> Cancelar</button>
                                <button @click="salvarEdicao" class="mt-btn-primary mt-btn-sm"><Save :size="12" /> Salvar</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .mt-page {
        --accent: #6d5dfc;
        --glow:   rgba(109,93,252,0.22);
        --purple: #a78bfa;
        --green:  #34d399;
        --bg:     #0d1117;
        --surface: #13192a;
        --border: #1e2840;
        --border-h: #2a3758;
        --t1: #eaedf5;
        --t2: #8892ab;
        --t3: #4a5470;
        --inp-bg: #0b0f1a;
        font-family: 'DM Sans', sans-serif;
        padding: 2rem;
        max-width: 760px;
        margin: 0 auto;
    }

    /* ── Header ── */
    .mt-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.75rem;
        flex-wrap: wrap;
    }
    .mt-header-left { display: flex; align-items: flex-start; gap: 0.85rem; }
    .mt-icon-wrap {
        width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
        background: rgba(109,93,252,0.12); border: 1px solid rgba(109,93,252,0.25);
        color: var(--purple);
        display: flex; align-items: center; justify-content: center;
    }
    .mt-title {
        font-family: 'Syne', sans-serif; font-size: 1.35rem; font-weight: 700;
        color: var(--t1); margin: 0 0 0.2rem;
    }
    .mt-sub { font-size: 0.82rem; color: var(--t3); margin: 0; }

    .mt-btn-add {
        display: inline-flex; align-items: center; gap: 0.45rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 500;
        padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer;
        background: var(--accent); border: none; color: #fff;
        transition: background 0.18s, box-shadow 0.18s; white-space: nowrap;
    }
    .mt-btn-add:hover { background: #7c6efd; box-shadow: 0 0 16px var(--glow); }

    .mt-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.82rem;
        padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer;
        background: transparent; border: 1px solid var(--border); color: var(--t3);
        transition: border-color 0.18s, color 0.18s;
    }
    .mt-btn-ghost:hover { color: var(--t2); border-color: var(--border-h); }
    .mt-btn-sm { padding: 0.35rem 0.7rem; font-size: 0.76rem; }

    .mt-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.82rem; font-weight: 500;
        padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer;
        background: var(--accent); border: none; color: #fff;
        transition: background 0.18s, box-shadow 0.18s;
    }
    .mt-btn-primary:hover:not(:disabled) { background: #7c6efd; box-shadow: 0 0 14px var(--glow); }
    .mt-btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
    .mt-btn-sm.mt-btn-primary { padding: 0.35rem 0.7rem; font-size: 0.76rem; }

    /* ── Form ── */
    .mt-form-card {
        background: rgba(109,93,252,0.05);
        border: 1px solid rgba(109,93,252,0.2);
        border-radius: 14px;
        padding: 1.1rem 1.2rem;
        margin-bottom: 1.5rem;
        display: flex; flex-direction: column; gap: 0.7rem;
    }
    .mt-form-title { font-size: 0.84rem; font-weight: 600; color: var(--t2); margin: 0; }
    .mt-form-row { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }

    .mt-input {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 8px;
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.84rem;
        padding: 0.55rem 0.8rem; outline: none;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .mt-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(109,93,252,0.12); }
    .mt-input::placeholder { color: var(--t3); }
    .mt-input--grow { flex: 1; min-width: 160px; }
    .mt-input--num { width: 70px; text-align: center; flex-shrink: 0; }

    .mt-prazo-wrap {
        display: flex; align-items: center; gap: 0.4rem;
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 8px;
        padding: 0.45rem 0.7rem;
        color: var(--t3);
    }
    .mt-prazo-wrap .mt-input--num {
        background: transparent; border: none; padding: 0; outline: none;
        box-shadow: none !important;
    }
    .mt-prazo-icon { flex-shrink: 0; }
    .mt-prazo-label { font-size: 0.78rem; color: var(--t3); white-space: nowrap; }

    .mt-textarea {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 8px;
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.82rem;
        padding: 0.55rem 0.8rem; outline: none; resize: vertical; width: 100%; box-sizing: border-box;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .mt-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(109,93,252,0.12); }
    .mt-textarea::placeholder { color: var(--t3); }

    .mt-form-actions { display: flex; justify-content: flex-end; gap: 0.4rem; }

    /* ── Loading / Vazio ── */
    .mt-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 4rem 1rem;
        gap: 0.5rem; text-align: center; color: var(--t3); opacity: 0.5;
    }
    .mt-empty-title { font-size: 0.9rem; font-weight: 500; color: var(--t2); margin: 0.4rem 0 0; opacity: 1; }
    .mt-empty-sub   { font-size: 0.8rem; color: var(--t3); opacity: 1; margin: 0; }
    .mt-spinner {
        width: 22px; height: 22px;
        border: 2px solid var(--border); border-top-color: var(--accent);
        border-radius: 50%; animation: mt-spin 0.65s linear infinite;
    }
    @keyframes mt-spin { to { transform: rotate(360deg); } }

    /* ── Lista ── */
    .mt-list {
        display: flex; flex-direction: column;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }

    .mt-item {
        display: flex; align-items: flex-start; gap: 0.85rem;
        padding: 0.9rem 1.1rem;
        border-bottom: 1px solid var(--border);
        animation: mt-fade 0.28s cubic-bezier(0.22,1,0.36,1) both;
        transition: background 0.12s;
    }
    .mt-item:last-child { border-bottom: none; }
    .mt-item:hover { background: rgba(255,255,255,0.015); }
    @keyframes mt-fade { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:translateY(0); } }

    .mt-item-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--accent); flex-shrink: 0; margin-top: 0.45rem;
        opacity: 0.7;
    }
    .mt-item-body { flex: 1; min-width: 0; }
    .mt-item-titulo { font-size: 0.87rem; font-weight: 500; color: var(--t1); margin: 0 0 0.15rem; word-break: break-word; }
    .mt-item-nota   { font-size: 0.76rem; color: var(--t3); margin: 0; word-break: break-word; }

    .mt-item-prazo {
        display: inline-flex; align-items: center; gap: 0.3rem;
        font-size: 0.72rem; font-weight: 600; white-space: nowrap;
        color: var(--purple);
        background: rgba(109,93,252,0.1);
        border: 1px solid rgba(109,93,252,0.2);
        padding: 0.2rem 0.55rem; border-radius: 100px;
        flex-shrink: 0;
    }

    .mt-item-actions { display: flex; gap: 0.3rem; flex-shrink: 0; }
    .mt-icon-btn {
        width: 28px; height: 28px; border-radius: 7px;
        border: 1px solid var(--border); background: transparent;
        cursor: pointer; color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        transition: color 0.14s, border-color 0.14s, background 0.14s;
    }
    .mt-icon-btn--edit:hover { color: var(--accent); border-color: rgba(109,93,252,0.4); background: rgba(109,93,252,0.08); }
    .mt-icon-btn--del:hover  { color: #f06292;       border-color: rgba(240,98,146,0.4); background: rgba(240,98,146,0.08); }

    .mt-edit-wrap { flex: 1; display: flex; flex-direction: column; gap: 0.55rem; }

    /* Transitions */
    .mt-slide-enter-active, .mt-slide-leave-active { transition: opacity 0.2s, transform 0.2s; }
    .mt-slide-enter-from, .mt-slide-leave-to { opacity: 0; transform: translateY(-6px); }

    /* Responsive */
    @media (max-width: 640px) {
        .mt-page { padding: 1.2rem; }
        .mt-item { flex-wrap: wrap; }
        .mt-item-prazo { order: -1; margin-left: 16px; }
    }
</style>
