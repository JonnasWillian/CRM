<script setup>
    import { ref, computed, onMounted } from 'vue';
    import axios from 'axios';
    import { PlusCircle, X, Save, Edit, Trash2, CheckCircle2, Circle, CalendarClock } from 'lucide-vue-next';
    import Swal from 'sweetalert2';

    const props = defineProps({ usuarioId: { type: [Number, String], required: true } });

    const tarefas   = ref([]);
    const isLoading = ref(false);
    const showForm  = ref(false);
    const form      = ref({ titulo: '', data_limite: '', anotacao: '' });
    const editingId = ref(null);
    const editForm  = ref({ titulo: '', data_limite: '', anotacao: '' });

    // Parse date string as local date (avoid UTC midnight timezone shift)
    const parseLocalDate = (iso) => {
        if (!iso) return null;
        const [y, m, d] = String(iso).substring(0, 10).split('-').map(Number);
        return new Date(y, m - 1, d);
    };

    const hojeLocal = () => {
        const d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    };

    const getDateStatus = (dataLimite) => {
        if (!dataLimite) return 'futura';
        const limite = parseLocalDate(dataLimite);
        const hoje   = hojeLocal();
        if (limite < hoje)                        return 'vencida';
        if (limite.getTime() === hoje.getTime())  return 'hoje';
        return 'futura';
    };

    const formatarData = (iso) => {
        if (!iso) return '';
        return parseLocalDate(iso).toLocaleDateString('pt-BR');
    };

    const formatarDataHora = (ts) => {
        if (!ts) return '';
        const d = new Date(ts);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const pendentes = computed(() =>
        tarefas.value
            .filter(t => !t.concluido)
            .slice()
            .sort((a, b) => parseLocalDate(a.data_limite) - parseLocalDate(b.data_limite))
    );

    const concluidas = computed(() =>
        tarefas.value
            .filter(t => t.concluido)
            .slice()
            .sort((a, b) => new Date(b.concluido_em || 0) - new Date(a.concluido_em || 0))
    );

    const buscarTarefas = async () => {
        isLoading.value = true;
        try {
            const res = await axios.get(`/api/tarefas/${props.usuarioId}`);
            tarefas.value = res.data;
        } catch {
            tarefas.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    const criarTarefa = async () => {
        if (!form.value.titulo.trim() || !form.value.data_limite) return;
        try {
            await axios.post('/api/tarefas', { ...form.value, usuario_id: props.usuarioId });
            form.value   = { titulo: '', data_limite: '', anotacao: '' };
            showForm.value = false;
            await buscarTarefas();
        } catch { /* silencioso */ }
    };

    const toggleConcluido = async (tarefa) => {
        const novo = !tarefa.concluido;
        tarefa.concluido    = novo;
        tarefa.concluido_em = novo ? new Date().toISOString() : null;
        try {
            await axios.put(`/api/tarefas/${tarefa.id}`, { concluido: novo });
        } catch {
            tarefa.concluido    = !novo;
            tarefa.concluido_em = null;
        }
    };

    const iniciarEdicao = (tarefa) => {
        editingId.value = tarefa.id;
        editForm.value  = {
            titulo:      tarefa.titulo,
            data_limite: String(tarefa.data_limite).substring(0, 10),
            anotacao:    tarefa.anotacao || '',
        };
    };

    const cancelarEdicao = () => { editingId.value = null; };

    const salvarEdicao = async () => {
        if (!editForm.value.titulo.trim() || !editForm.value.data_limite) return;
        try {
            await axios.put(`/api/tarefas/${editingId.value}`, editForm.value);
            editingId.value = null;
            await buscarTarefas();
        } catch { /* silencioso */ }
    };

    const excluirTarefa = async (id) => {
        const result = await Swal.fire({
            title: 'Excluir tarefa?',
            text: 'Esta ação não pode ser desfeita.',
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
            tarefas.value = tarefas.value.filter(t => t.id !== id);
            try { await axios.delete(`/api/tarefas/${id}`); }
            catch { await buscarTarefas(); }
        }
    };

    onMounted(buscarTarefas);
</script>

<template>
    <div class="tp-wrap">

        <!-- Header -->
        <div class="tp-header">
            <div class="tp-header-left">
                <span class="tp-count tp-count--pending" v-if="pendentes.length">{{ pendentes.length }} pendente{{ pendentes.length !== 1 ? 's' : '' }}</span>
                <span class="tp-count tp-count--done"    v-if="concluidas.length">{{ concluidas.length }} concluída{{ concluidas.length !== 1 ? 's' : '' }}</span>
            </div>
            <button v-if="!showForm" @click="showForm = true" class="tp-btn-add" title="Nova tarefa">
                <PlusCircle :size="14" /> Nova tarefa
            </button>
            <button v-else @click="showForm = false; form = { titulo:'', data_limite:'', anotacao:'' }" class="tp-btn-ghost">
                <X :size="14" /> Cancelar
            </button>
        </div>

        <!-- Form inline -->
        <Transition name="tp-slide">
            <div v-if="showForm" class="tp-form">
                <div class="tp-form-row">
                    <input
                        v-model="form.titulo"
                        type="text"
                        placeholder="Título da tarefa..."
                        class="tp-input tp-input--grow"
                        autofocus
                        @keydown.enter="criarTarefa"
                    />
                    <input
                        v-model="form.data_limite"
                        type="date"
                        class="tp-input tp-input--date"
                    />
                </div>
                <textarea
                    v-model="form.anotacao"
                    placeholder="Anotação (opcional)..."
                    rows="2"
                    class="tp-textarea"
                />
                <div class="tp-form-actions">
                    <button @click="showForm = false; form = { titulo:'', data_limite:'', anotacao:'' }" class="tp-btn-ghost tp-btn-sm">
                        <X :size="12" /> Cancelar
                    </button>
                    <button @click="criarTarefa" class="tp-btn-primary tp-btn-sm" :disabled="!form.titulo.trim() || !form.data_limite">
                        <Save :size="12" /> Salvar tarefa
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Loading -->
        <div v-if="isLoading" class="tp-empty">
            <div class="tp-spinner" />
            <span>Carregando tarefas...</span>
        </div>

        <!-- Vazio -->
        <div v-else-if="!pendentes.length && !concluidas.length && !showForm" class="tp-empty">
            <CalendarClock :size="28" />
            <p class="tp-empty-title">Nenhuma tarefa registrada</p>
            <p class="tp-empty-sub">Clique em "Nova tarefa" para adicionar um follow-up.</p>
        </div>

        <!-- Lista de pendentes -->
        <div v-else class="tp-list">
            <div
                v-for="(tarefa, i) in pendentes"
                :key="tarefa.id"
                class="tp-item"
                :style="{ animationDelay: `${i * 0.04}s` }"
            >
                <!-- Modo visualização -->
                <template v-if="editingId !== tarefa.id">
                    <button class="tp-checkbox" @click="toggleConcluido(tarefa)" :title="'Marcar como concluída'">
                        <Circle :size="18" />
                    </button>
                    <div class="tp-item-body">
                        <p class="tp-item-title">{{ tarefa.titulo }}</p>
                        <p v-if="tarefa.anotacao" class="tp-item-note">{{ tarefa.anotacao }}</p>
                    </div>
                    <span
                        class="tp-date-badge"
                        :class="`tp-date-badge--${getDateStatus(tarefa.data_limite)}`"
                    >
                        {{ getDateStatus(tarefa.data_limite) === 'vencida' ? '⚠ ' : '' }}{{ formatarData(tarefa.data_limite) }}
                    </span>
                    <div class="tp-item-actions">
                        <button @click="iniciarEdicao(tarefa)" class="tp-icon-btn tp-icon-btn--edit" title="Editar"><Edit :size="12" /></button>
                        <button @click="excluirTarefa(tarefa.id)" class="tp-icon-btn tp-icon-btn--del" title="Excluir"><Trash2 :size="12" /></button>
                    </div>
                </template>

                <!-- Modo edição inline -->
                <template v-else>
                    <div class="tp-edit-wrap">
                        <div class="tp-form-row">
                            <input v-model="editForm.titulo" type="text" class="tp-input tp-input--grow" />
                            <input v-model="editForm.data_limite" type="date" class="tp-input tp-input--date" />
                        </div>
                        <textarea v-model="editForm.anotacao" rows="2" class="tp-textarea" placeholder="Anotação (opcional)..." />
                        <div class="tp-form-actions">
                            <button @click="cancelarEdicao" class="tp-btn-ghost tp-btn-sm"><X :size="12" /> Cancelar</button>
                            <button @click="salvarEdicao" class="tp-btn-primary tp-btn-sm"><Save :size="12" /> Salvar</button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Separador e concluídas -->
            <template v-if="concluidas.length">
                <div class="tp-sep">
                    <span>Concluídas ({{ concluidas.length }})</span>
                </div>
                <div
                    v-for="tarefa in concluidas"
                    :key="tarefa.id"
                    class="tp-item tp-item--done"
                >
                    <button class="tp-checkbox tp-checkbox--done" @click="toggleConcluido(tarefa)" title="Desmarcar">
                        <CheckCircle2 :size="18" />
                    </button>
                    <div class="tp-item-body">
                        <p class="tp-item-title tp-item-title--done">{{ tarefa.titulo }}</p>
                        <p v-if="tarefa.concluido_em" class="tp-item-note">Concluída em {{ formatarDataHora(tarefa.concluido_em) }}</p>
                    </div>
                    <button @click="excluirTarefa(tarefa.id)" class="tp-icon-btn tp-icon-btn--del" title="Excluir"><Trash2 :size="12" /></button>
                </div>
            </template>
        </div>

    </div>
</template>

<style scoped>
    .tp-wrap {
        --amber:  #f59e0b;
        --red:    #ef4444;
        --green:  #34d399;
        --purple: #a78bfa;
        --accent: #6d5dfc;
        --glow:   rgba(109,93,252,0.22);
        --bg:     #0d1117;
        --surface: #13192a;
        --border: #1e2840;
        --border-h: #2a3758;
        --t1: #eaedf5;
        --t2: #8892ab;
        --t3: #4a5470;
        --inp-bg: #0b0f1a;
        font-family: 'DM Sans', sans-serif;
    }

    /* ── Header ── */
    .tp-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .tp-header-left { display: flex; gap: 0.4rem; flex-wrap: wrap; }

    .tp-count {
        font-size: 0.68rem; font-weight: 600;
        padding: 0.15rem 0.55rem; border-radius: 100px;
        border: 1px solid;
    }
    .tp-count--pending { color: var(--amber); background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.25); }
    .tp-count--done    { color: var(--green); background: rgba(52,211,153,0.1); border-color: rgba(52,211,153,0.25); }

    .tp-btn-add {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500;
        padding: 0.4rem 0.85rem; border-radius: 8px; cursor: pointer;
        background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); color: var(--amber);
        transition: background 0.18s, border-color 0.18s;
    }
    .tp-btn-add:hover { background: rgba(245,158,11,0.18); border-color: rgba(245,158,11,0.5); }

    .tp-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem;
        padding: 0.4rem 0.85rem; border-radius: 8px; cursor: pointer;
        background: transparent; border: 1px solid var(--border); color: var(--t3);
        transition: border-color 0.18s, color 0.18s;
    }
    .tp-btn-ghost:hover { color: var(--t2); border-color: var(--border-h); }
    .tp-btn-sm { padding: 0.35rem 0.7rem; font-size: 0.75rem; }

    .tp-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500;
        padding: 0.4rem 0.85rem; border-radius: 8px; cursor: pointer;
        background: var(--accent); border: none; color: #fff;
        transition: background 0.18s, box-shadow 0.18s;
    }
    .tp-btn-primary:hover:not(:disabled) { background: #7c6efd; box-shadow: 0 0 14px var(--glow); }
    .tp-btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
    .tp-btn-sm.tp-btn-primary { padding: 0.35rem 0.7rem; font-size: 0.75rem; }

    /* ── Form ── */
    .tp-form {
        background: rgba(245,158,11,0.04);
        border: 1px solid rgba(245,158,11,0.18);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        margin-bottom: 1rem;
        display: flex; flex-direction: column; gap: 0.6rem;
    }
    .tp-form-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .tp-input {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 8px;
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.84rem;
        padding: 0.55rem 0.8rem; outline: none;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .tp-input:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
    .tp-input::placeholder { color: var(--t3); }
    .tp-input--grow { flex: 1; min-width: 160px; }
    .tp-input--date { width: 150px; flex-shrink: 0; color-scheme: dark; }

    .tp-textarea {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 8px;
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.82rem;
        padding: 0.55rem 0.8rem; outline: none; resize: vertical; width: 100%; box-sizing: border-box;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .tp-textarea:focus { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
    .tp-textarea::placeholder { color: var(--t3); }

    .tp-form-actions { display: flex; justify-content: flex-end; gap: 0.4rem; }

    /* ── Loading / Vazio ── */
    .tp-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 2.5rem 1rem;
        gap: 0.4rem; text-align: center; color: var(--t3); opacity: 0.55;
    }
    .tp-empty-title { font-size: 0.875rem; font-weight: 500; color: var(--t2); opacity: 1; margin: 0.3rem 0 0; }
    .tp-empty-sub   { font-size: 0.78rem; color: var(--t3); opacity: 1; margin: 0; }
    .tp-spinner {
        width: 20px; height: 20px;
        border: 2px solid var(--border); border-top-color: var(--amber);
        border-radius: 50%; animation: tp-spin 0.65s linear infinite;
    }
    @keyframes tp-spin { to { transform: rotate(360deg); } }

    /* ── Lista ── */
    .tp-list { display: flex; flex-direction: column; gap: 0; }

    .tp-item {
        display: flex; align-items: flex-start; gap: 0.65rem;
        padding: 0.75rem 0; border-bottom: 1px solid rgba(30,40,64,0.5);
        animation: tp-fade 0.28s cubic-bezier(0.22,1,0.36,1) both;
        transition: background 0.12s;
    }
    .tp-item:last-child { border-bottom: none; }
    .tp-item--done { opacity: 0.6; }
    @keyframes tp-fade { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }

    /* Checkbox */
    .tp-checkbox {
        flex-shrink: 0; margin-top: 1px;
        background: transparent; border: none; cursor: pointer;
        color: var(--t3); padding: 0;
        transition: color 0.15s, transform 0.15s;
        display: flex; align-items: center;
    }
    .tp-checkbox:hover { color: var(--amber); transform: scale(1.15); }
    .tp-checkbox--done { color: var(--green); }
    .tp-checkbox--done:hover { color: #6ee7b7; }

    /* Item body */
    .tp-item-body { flex: 1; min-width: 0; }
    .tp-item-title { font-size: 0.85rem; font-weight: 500; color: var(--t1); margin: 0 0 0.15rem; word-break: break-word; }
    .tp-item-title--done { text-decoration: line-through; color: var(--t3); }
    .tp-item-note { font-size: 0.76rem; color: var(--t3); margin: 0; word-break: break-word; }

    /* Date badge */
    .tp-date-badge {
        flex-shrink: 0; font-size: 0.68rem; font-weight: 600;
        padding: 0.2rem 0.55rem; border-radius: 100px; white-space: nowrap;
        border: 1px solid;
    }
    .tp-date-badge--vencida { color: var(--red);   background: rgba(239,68,68,0.1);   border-color: rgba(239,68,68,0.3); }
    .tp-date-badge--hoje    { color: var(--amber);  background: rgba(245,158,11,0.1);  border-color: rgba(245,158,11,0.3); }
    .tp-date-badge--futura  { color: var(--t3);     background: rgba(255,255,255,0.04); border-color: var(--border); }

    /* Action buttons */
    .tp-item-actions { display: flex; gap: 0.3rem; flex-shrink: 0; }
    .tp-icon-btn {
        width: 24px; height: 24px; border-radius: 6px;
        border: 1px solid var(--border); background: transparent;
        cursor: pointer; color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        transition: color 0.14s, border-color 0.14s, background 0.14s;
    }
    .tp-icon-btn--edit:hover { color: var(--accent); border-color: rgba(109,93,252,0.4); background: rgba(109,93,252,0.08); }
    .tp-icon-btn--del:hover  { color: #f06292;       border-color: rgba(240,98,146,0.4); background: rgba(240,98,146,0.08); }

    /* Edição inline */
    .tp-edit-wrap { flex: 1; display: flex; flex-direction: column; gap: 0.55rem; }

    /* Separador concluídas */
    .tp-sep {
        display: flex; align-items: center; gap: 0.6rem;
        margin: 0.75rem 0 0.25rem;
        font-size: 0.7rem; font-weight: 500; color: var(--t3);
        letter-spacing: 0.06em; text-transform: uppercase;
    }
    .tp-sep::before, .tp-sep::after {
        content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* Transitions */
    .tp-slide-enter-active, .tp-slide-leave-active { transition: opacity 0.2s, transform 0.2s; }
    .tp-slide-enter-from, .tp-slide-leave-to { opacity: 0; transform: translateY(-6px); }

    /* Responsive */
    @media (max-width: 520px) {
        .tp-item { flex-wrap: wrap; }
        .tp-date-badge { order: -1; margin-left: 26px; }
    }
</style>
