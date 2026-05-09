<script setup>
    import { ref, watch, onMounted } from 'vue';
    import axios from 'axios';
    import { PlusCircle, FolderOpen, Save, X, Edit, Trash2, AlertCircle, CheckCircle2, ChevronDown, Layers, DollarSign, Calendar, CreditCard } from 'lucide-vue-next';
    import Swal from 'sweetalert2';

    const props = defineProps({
        usuarioId: {
            type: [Number, String],
            required: true,
        },
    });

    const emit = defineEmits(['update:total']);

    const projetos  = ref([]);
    const status    = ref([]);
    const showForm  = ref(false);
    const editingId = ref(null);
    const isSaving  = ref(false);
    const isLoading = ref(true);
    const toast     = ref({ show: false, message: '', type: 'success' });

    const emptyForm = () => ({
        nome:         '',
        descricao:    '',
        status_id:    '',
        usuario_id:      props.usuarioId,
        preco:        '',
        data_inicial: '',
        data_final:   '',
        parcelas:     false,
        qtd_parcelas: '',
    });

    const emptyEditForm = () => ({
        nome:         '',
        descricao:    '',
        status_id:    '',
        preco:        '',
        data_inicial: '',
        data_final:   '',
        parcelas:     false,
        qtd_parcelas: '',
    });

    const form     = ref(emptyForm());
    const editForm = ref(emptyEditForm());

    watch(() => form.value.parcelas,     (v) => { if (!v) form.value.qtd_parcelas     = ''; });
    watch(() => editForm.value.parcelas, (v) => { if (!v) editForm.value.qtd_parcelas = ''; });

    let toastTimer = null;
    const showToast = (message, type = 'success') => {
        clearTimeout(toastTimer);
        toast.value = { show: true, message, type };
        toastTimer = setTimeout(() => { toast.value.show = false; }, 3500);
    };

    const statusColorMap = {
        1: { color: '#60a5fa', bg: 'rgba(96,165,250,0.12)'  },
        2: { color: '#f59e0b', bg: 'rgba(245,158,11,0.12)'  },
        3: { color: '#34d399', bg: 'rgba(52,211,153,0.12)'  },
        4: { color: '#ef4444', bg: 'rgba(239,68,68,0.12)'   },
        5: { color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)'  },
        6: { color: '#ec4899', bg: 'rgba(236,72,153,0.12)'  },
    };
    const getStatusStyle = (statusId) => {
        const idx = ((Number(statusId) - 1) % 6) + 1;
        return statusColorMap[idx] || { color: '#94a3b8', bg: 'rgba(148,163,184,0.12)' };
    };

    const formatarPreco = (centavos) => {
        if (!centavos && centavos !== 0) return '—';
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(centavos / 1);
    };

    const formatarData = (iso) => {
        if (!iso) return null;
        const d = new Date(iso);
        return isNaN(d) ? null : d.toLocaleDateString('pt-BR');
    };

    const precoParaCentavos = (val) => {
        if (val === '' || val === null || val === undefined) return null;
        const n = parseFloat(String(val).replace(',', '.'));
        return n;
    };

    const centavosParaInput = (centavos) => {
        if (!centavos && centavos !== 0) return '';
        return (centavos / 1).toFixed(2).replace('.', ',');
    };

    const montarPayload = (f) => ({
        ...f,
        preco:        precoParaCentavos(f.preco),
        parcelas:     !!f.parcelas,
        qtd_parcelas: f.parcelas ? (Number(f.qtd_parcelas) || null) : null,
        data_inicial: f.data_inicial || null,
        data_final:   f.data_final   || null,
    });

    const buscarStatus = async () => {
        try {
            const res = await axios.post('/api/status');
            status.value = res.data;
        } catch {
            showToast('Erro ao buscar status!', 'error');
        }
    };

    const buscarProjetos = async () => {
        isLoading.value = true;
        try {
            const res = await axios.post('/api/projetos', { usuario_id: props.usuarioId });
            projetos.value = res.data;
            emit('update:total', projetos.value.length);
        } catch {
            showToast('Erro ao buscar projetos!', 'error');
        } finally {
            isLoading.value = false;
        }
    };

    const cadastrarProjeto = async () => {
        if (!form.value.nome.trim() || !form.value.status_id) return;
        isSaving.value = true;
        try {
            await axios.post('/api/projeto', montarPayload(form.value));
            showToast('Projeto criado com sucesso!');
            resetForm();
            await buscarProjetos();
        } catch (err) {
            const erros = err?.response?.data?.erros;
            showToast(erros ? Object.values(erros).flat().join(' | ') : 'Erro ao criar projeto!', 'error');
        } finally {
            isSaving.value = false;
        }
    };

    const iniciarEdicao = (projeto) => {
        editingId.value = projeto.id;
        editForm.value = {
            nome:         projeto.nome,
            descricao:    projeto.descricao    || '',
            status_id:    projeto.status_id,
            preco:        centavosParaInput(projeto.preco),
            data_inicial: projeto.data_inicial ? projeto.data_inicial.slice(0, 10) : '',
            data_final:   projeto.data_final   ? projeto.data_final.slice(0, 10)   : '',
            parcelas:     !!projeto.parcelas,
            qtd_parcelas: projeto.qtd_parcelas || '',
        };
    };

    const cancelarEdicao = () => { editingId.value = null; };

    const salvarEdicao = async () => {
        if (!editForm.value.nome.trim() || !editForm.value.status_id) return;
        isSaving.value = true;
        try {
            await axios.put(`/api/projeto/${editingId.value}`, montarPayload(editForm.value));
            showToast('Projeto atualizado!');
            editingId.value = null;
            await buscarProjetos();
        } catch (err) {
            const erros = err?.response?.data?.erros;
            showToast(erros ? Object.values(erros).flat().join(' | ') : 'Erro ao atualizar projeto!', 'error');
        } finally {
            isSaving.value = false;
        }
    };

    const excluirProjeto = async (id) => {
        const result = await Swal.fire({
            title: 'Apagar projeto?',
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
            try {
                await axios.delete(`/api/projeto/${id}`);
                showToast('Projeto removido.');
                await buscarProjetos();
            } catch {
                showToast('Erro ao apagar projeto!', 'error');
            }
        }
    };

    const resetForm = () => {
        form.value = emptyForm();
        showForm.value = false;
    };

    onMounted(async () => {
        await Promise.all([buscarStatus(), buscarProjetos()]);
    });
</script>

<template>
    <Transition name="proj-toast">
        <div v-if="toast.show" class="proj-toast" :class="`proj-toast--${toast.type}`">
            <CheckCircle2 v-if="toast.type === 'success'" :size="14" />
            <AlertCircle v-else :size="14" />
            {{ toast.message }}
        </div>
    </Transition>

    <section class="proj-panel">
        <div class="proj-panel-header">
            <div class="proj-panel-title-group">
                <span class="proj-panel-icon"><Layers :size="14" /></span>
                <h2 class="proj-panel-title">Projetos</h2>
                <span class="proj-count-chip">{{ projetos.length }}</span>
            </div>
            <button v-if="!showForm" @click="showForm = true" class="proj-btn-add">
                <PlusCircle :size="14" /> Novo
            </button>
            <button v-else @click="resetForm" class="proj-btn-cancel-header">
                <X :size="14" />
            </button>
        </div>

        <Transition name="proj-slide">
            <div v-if="showForm" class="proj-form-zone">
                <div class="proj-grid proj-grid--2">
                    <div class="proj-field">
                        <label class="proj-label">Nome do projeto *</label>
                        <input v-model="form.nome" type="text" class="proj-input" placeholder="Ex: Site institucional..." maxlength="120" />
                    </div>
                    <div class="proj-field">
                        <label class="proj-label">Status *</label>
                        <div class="proj-select-wrap">
                            <select v-model="form.status_id" class="proj-input proj-select">
                                <option value="">Selecione um status</option>
                                <option v-for="s in status" :key="s.id" :value="s.id">{{ s.descricao }}</option>
                            </select>
                            <ChevronDown :size="13" class="proj-select-arrow" />
                        </div>
                    </div>
                </div>
                <div class="proj-field">
                    <label class="proj-label">Descrição</label>
                    <textarea v-model="form.descricao" rows="2" class="proj-input proj-textarea" placeholder="Descreva brevemente o projeto..." />
                </div>

                <div class="proj-grid proj-grid--3">
                    <div class="proj-field">
                        <label class="proj-label"><DollarSign :size="11" /> Valor (R$)</label>
                        <input v-model="form.preco" type="text" inputmode="decimal" class="proj-input" placeholder="Ex: 1.500,00" />
                    </div>
                    <div class="proj-field">
                        <label class="proj-label"><Calendar :size="11" /> Data inicial</label>
                        <input v-model="form.data_inicial" type="date" class="proj-input proj-input--date" />
                    </div>
                    <div class="proj-field">
                        <label class="proj-label"><Calendar :size="11" /> Data final</label>
                        <input v-model="form.data_final" type="date" class="proj-input proj-input--date" />
                    </div>
                </div>

                <div class="proj-parcelas-bar">
                    <label class="proj-toggle-label">
                        <span class="proj-toggle-wrap">
                            <input type="checkbox" v-model="form.parcelas" class="proj-toggle-input" />
                            <span class="proj-toggle-track"><span class="proj-toggle-thumb" /></span>
                        </span>
                        <CreditCard :size="13" />
                        Parcelado
                    </label>
                    <Transition name="proj-slide">
                        <div v-if="form.parcelas" class="proj-field proj-field--row">
                            <label class="proj-label">Nº de parcelas</label>
                            <input v-model="form.qtd_parcelas" type="number" min="2" max="120" class="proj-input proj-input--narrow" placeholder="12" />
                        </div>
                    </Transition>
                </div>

                <div class="proj-actions">
                    <button @click="resetForm" class="proj-btn-ghost proj-btn-sm"><X :size="12" /> Cancelar</button>
                    <button @click="cadastrarProjeto" class="proj-btn-primary proj-btn-sm" :disabled="isSaving || !form.nome.trim() || !form.status_id">
                        <span v-if="isSaving" class="proj-spinner" /><Save v-else :size="12" />
                        {{ isSaving ? 'Salvando...' : 'Criar projeto' }}
                    </button>
                </div>
            </div>
        </Transition>

        <div class="proj-body">

            <div v-if="isLoading" class="proj-loading">
                <div class="proj-spinner proj-spinner--lg" />
            </div>

            <div v-else-if="projetos.length > 0" class="proj-list">
                <div v-for="(projeto, i) in projetos" :key="projeto.id" class="proj-item" :style="{ animationDelay: `${i * 0.045}s` }">

                    <template v-if="editingId !== projeto.id">
                        <div class="proj-view-row">
                            <div class="proj-item-left">
                                <span class="proj-dot" :style="{ background: getStatusStyle(projeto.status_id).color, boxShadow: `0 0 6px ${getStatusStyle(projeto.status_id).color}55` }" />
                                <div class="proj-item-info">
                                    <span class="proj-item-name">{{ projeto.nome }}</span>
                                    <span v-if="projeto.descricao" class="proj-item-desc">{{ projeto.descricao }}</span>
                                    <div class="proj-chips">
                                        <span v-if="projeto.preco" class="proj-chip proj-chip--green">
                                            <DollarSign :size="10" />{{ formatarPreco(projeto.preco) }}
                                        </span>
                                        <span v-if="formatarData(projeto.data_inicial)" class="proj-chip">
                                            <Calendar :size="10" />{{ formatarData(projeto.data_inicial) }}
                                        </span>
                                        <span v-if="formatarData(projeto.data_final)" class="proj-chip">
                                            → {{ formatarData(projeto.data_final) }}
                                        </span>
                                        <span v-if="projeto.parcelas && projeto.qtd_parcelas" class="proj-chip proj-chip--purple">
                                            <CreditCard :size="10" />{{ projeto.qtd_parcelas }}x
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="proj-item-right">
                                <span class="proj-status-badge" :style="{ color: getStatusStyle(projeto.status_id).color, background: getStatusStyle(projeto.status_id).bg }">
                                    {{ projeto.status?.descricao || '—' }}
                                </span>
                                <div class="proj-item-btns">
                                    <button @click="iniciarEdicao(projeto)" class="proj-icon-btn proj-icon-btn--edit" title="Editar"><Edit :size="12" /></button>
                                    <button @click="excluirProjeto(projeto.id)" class="proj-icon-btn proj-icon-btn--delete" title="Excluir"><Trash2 :size="12" /></button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="proj-edit-zone">
                            <div class="proj-grid proj-grid--2">
                                <div class="proj-field">
                                    <label class="proj-label">Nome *</label>
                                    <input v-model="editForm.nome" type="text" class="proj-input" />
                                </div>
                                <div class="proj-field">
                                    <label class="proj-label">Status *</label>
                                    <div class="proj-select-wrap">
                                        <select v-model="editForm.status_id" class="proj-input proj-select">
                                            <option v-for="s in status" :key="s.id" :value="s.id">{{ s.descricao }}</option>
                                        </select>
                                        <ChevronDown :size="13" class="proj-select-arrow" />
                                    </div>
                                </div>
                            </div>
                            <div class="proj-field">
                                <label class="proj-label">Descrição</label>
                                <textarea v-model="editForm.descricao" rows="2" class="proj-input proj-textarea" />
                            </div>
                            <div class="proj-grid proj-grid--3">
                                <div class="proj-field">
                                    <label class="proj-label"><DollarSign :size="11" /> Valor (R$)</label>
                                    <input v-model="editForm.preco" type="text" inputmode="decimal" class="proj-input" placeholder="Ex: 1.500,00" />
                                </div>
                                <div class="proj-field">
                                    <label class="proj-label"><Calendar :size="11" /> Data inicial</label>
                                    <input v-model="editForm.data_inicial" type="date" class="proj-input proj-input--date" />
                                </div>
                                <div class="proj-field">
                                    <label class="proj-label"><Calendar :size="11" /> Data final</label>
                                    <input v-model="editForm.data_final" type="date" class="proj-input proj-input--date" />
                                </div>
                            </div>
                            <div class="proj-parcelas-bar">
                                <label class="proj-toggle-label">
                                    <span class="proj-toggle-wrap">
                                        <input type="checkbox" v-model="editForm.parcelas" class="proj-toggle-input" />
                                        <span class="proj-toggle-track"><span class="proj-toggle-thumb" /></span>
                                    </span>
                                    <CreditCard :size="13" />
                                    Parcelado
                                </label>
                                <Transition name="proj-slide">
                                    <div v-if="editForm.parcelas" class="proj-field proj-field--row">
                                        <label class="proj-label">Nº de parcelas</label>
                                        <input v-model="editForm.qtd_parcelas" type="number" min="2" max="120" class="proj-input proj-input--narrow" placeholder="12" />
                                    </div>
                                </Transition>
                            </div>
                            <div class="proj-actions">
                                <button @click="cancelarEdicao" class="proj-btn-ghost proj-btn-sm"><X :size="12" /> Cancelar</button>
                                <button @click="salvarEdicao" class="proj-btn-primary proj-btn-sm" :disabled="isSaving">
                                    <span v-if="isSaving" class="proj-spinner" /><Save v-else :size="12" />
                                    Salvar
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div v-else-if="!showForm" class="proj-empty">
                <div class="proj-empty-icon"><FolderOpen :size="24" /></div>
                <p class="proj-empty-title">Nenhum projeto</p>
                <p class="proj-empty-sub">Clique em "Novo" para criar o primeiro projeto.</p>
            </div>
        </div>
    </section>
</template>

<style scoped>
    .proj-panel {
        --bg:        #0d1117;
        --surface:   #13192a;
        --border:    #1e2840;
        --border-h:  #2a3758;
        --accent:    #6d5dfc;
        --accent-h:  #7c6efd;
        --glow:      rgba(109, 93, 252, 0.22);
        --green:     #3ecf8e;
        --green-bg:  rgba(62, 207, 142, 0.1);
        --red:       #f06292;
        --red-bg:    rgba(240, 98, 146, 0.1);
        --orange:    #fb923c;
        --orange-bg: rgba(251, 146, 60, 0.1);
        --purple:    #a78bfa;
        --purple-bg: rgba(167, 139, 250, 0.1);
        --t1: #eaedf5; --t2: #8892ab; --t3: #4a5470;
        --inp-bg: #0b0f1a;

        font-family: 'DM Sans', sans-serif;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        display: flex; flex-direction: column;
        position: relative;
    }

    /* ── Toast ── */
    .proj-toast {
        position: absolute; top: 0.75rem; right: 1rem; z-index: 100;
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 0.9rem; border-radius: 8px;
        font-size: 0.78rem; font-weight: 500;
        backdrop-filter: blur(10px); border: 1px solid transparent;
        box-shadow: 0 4px 16px rgba(0,0,0,0.35); pointer-events: none;
    }
    .proj-toast--success { background: rgba(62,207,142,0.15); border-color: rgba(62,207,142,0.3); color: var(--green); }
    .proj-toast--error   { background: rgba(240,98,146,0.15); border-color: rgba(240,98,146,0.3); color: var(--red); }
    .proj-toast-enter-active, .proj-toast-leave-active { transition: opacity .2s, transform .2s; }
    .proj-toast-enter-from, .proj-toast-leave-to { opacity: 0; transform: translateY(-6px); }

    /* ── Header ── */
    .proj-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    }
    .proj-panel-title-group { display: flex; align-items: center; gap: .55rem; }
    .proj-panel-icon {
        width: 26px; height: 26px; border-radius: 7px;
        background: var(--orange-bg); color: var(--orange);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .proj-panel-title { font-family: 'Syne', sans-serif; font-size: .875rem; font-weight: 700; color: var(--t1); margin: 0; }
    .proj-count-chip {
        font-size: .68rem; font-weight: 600;
        background: var(--orange-bg); border: 1px solid rgba(251,146,60,.25);
        color: var(--orange); border-radius: 100px; padding: .1rem .5rem;
    }
    .proj-btn-add {
        display: inline-flex; align-items: center; gap: .35rem;
        background: var(--orange-bg); border: 1px solid rgba(251,146,60,.3);
        color: var(--orange); border-radius: 8px; padding: .38rem .75rem;
        font-family: 'DM Sans', sans-serif; font-size: .78rem; font-weight: 500;
        cursor: pointer; transition: background .2s, border-color .2s;
    }
    .proj-btn-add:hover { background: rgba(251,146,60,.2); border-color: rgba(251,146,60,.5); }
    .proj-btn-cancel-header {
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; background: transparent; border: 1px solid var(--border);
        color: var(--t3); cursor: pointer; transition: color .15s, border-color .15s;
    }
    .proj-btn-cancel-header:hover { color: var(--red); border-color: rgba(240,98,146,.4); }

    /* ── Form zone ── */
    .proj-form-zone {
        padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
        background: rgba(251,146,60,.025);
        display: flex; flex-direction: column; gap: .85rem;
    }
    .proj-grid { display: grid; gap: .75rem; }
    .proj-grid--2 { grid-template-columns: 1fr 1fr; }
    .proj-grid--3 { grid-template-columns: 1fr 1fr 1fr; }

    .proj-field { display: flex; flex-direction: column; gap: .35rem; }
    .proj-field--row { flex-direction: row; align-items: center; gap: .6rem; }
    .proj-field--row .proj-label { white-space: nowrap; }

    .proj-label {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .68rem; font-weight: 500;
        letter-spacing: .07em; text-transform: uppercase; color: var(--t3);
    }

    .proj-input {
        background: var(--inp-bg); border: 1px solid var(--border);
        border-radius: 9px; color: var(--t1);
        font-family: 'DM Sans', sans-serif; font-size: .85rem;
        padding: .55rem .8rem; outline: none;
        transition: border-color .2s, box-shadow .2s;
        width: 100%; box-sizing: border-box;
    }
    .proj-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(251,146,60,.15); }
    .proj-input::placeholder { color: var(--t3); }
    .proj-textarea { resize: vertical; min-height: 60px; }
    .proj-input--date { cursor: pointer; color-scheme: dark; }
    .proj-input--narrow { width: 100px; flex-shrink: 0; }

    .proj-select-wrap { position: relative; display: flex; align-items: center; }
    .proj-select { appearance: none; -webkit-appearance: none; cursor: pointer; padding-right: 2rem; }
    .proj-select option { background: #0b0f1a; color: #eaedf5; }
    .proj-select-arrow { position: absolute; right: .7rem; pointer-events: none; color: var(--t3); }

    /* ── Toggle parcelas ── */
    .proj-parcelas-bar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
        padding: .65rem .9rem;
        background: rgba(255,255,255,.02);
        border: 1px solid var(--border); border-radius: 9px;
    }
    .proj-toggle-label {
        display: inline-flex; align-items: center; gap: .5rem;
        cursor: pointer; font-size: .82rem; color: var(--t2); user-select: none;
    }
    .proj-toggle-wrap { position: relative; display: inline-flex; align-items: center; }
    .proj-toggle-input { position: absolute; opacity: 0; width: 0; height: 0; }
    .proj-toggle-track {
        display: block; width: 34px; height: 18px;
        background: var(--border); border-radius: 100px;
        border: 1px solid var(--border-h);
        transition: background .2s, border-color .2s; position: relative;
    }
    .proj-toggle-input:checked + .proj-toggle-track { background: rgba(109,93,252,.4); border-color: var(--accent); }
    .proj-toggle-thumb {
        position: absolute; top: 2px; left: 2px;
        width: 12px; height: 12px; background: var(--t3);
        border-radius: 50%; transition: transform .2s, background .2s;
    }
    .proj-toggle-input:checked + .proj-toggle-track .proj-toggle-thumb { transform: translateX(16px); background: var(--accent); }

    .proj-actions { display: flex; justify-content: flex-end; gap: .5rem; }

    /* ── Body / list ── */
    .proj-body { flex: 1; max-height: 540px; overflow-y: auto; }
    .proj-body::-webkit-scrollbar { width: 4px; }
    .proj-body::-webkit-scrollbar-track { background: transparent; }
    .proj-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .proj-loading { display: flex; align-items: center; justify-content: center; padding: 3rem; }

    .proj-list { display: flex; flex-direction: column; }
    .proj-item {
        border-bottom: 1px solid rgba(30,40,64,.6);
        transition: background .15s;
        animation: projFade .3s cubic-bezier(.22,1,.36,1) both;
    }
    .proj-item:last-child { border-bottom: none; }
    .proj-item:hover { background: rgba(251,146,60,.02); }
    @keyframes projFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; } }

    /* ── View row ── */
    .proj-view-row {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: .75rem; padding: .9rem 1.25rem;
    }
    .proj-item-left { display: flex; align-items: flex-start; gap: .65rem; min-width: 0; flex: 1; }
    .proj-item-right { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; padding-top: .1rem; }

    .proj-dot {
        width: 8px; height: 8px; border-radius: 50%;
        flex-shrink: 0; margin-top: .4rem;
    }
    .proj-item-info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .proj-item-name {
        font-size: .85rem; font-weight: 500; color: var(--t1);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .proj-item-desc {
        font-size: .75rem; color: var(--t3);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 340px;
    }

    /* ── Meta chips ── */
    .proj-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .25rem; }
    .proj-chip {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .68rem; font-weight: 500;
        background: rgba(255,255,255,.04); border: 1px solid var(--border);
        color: var(--t3); border-radius: 100px; padding: .1rem .5rem;
    }
    .proj-chip--green  { background: var(--green-bg);  border-color: rgba(62,207,142,.2);  color: var(--green); }
    .proj-chip--purple { background: var(--purple-bg); border-color: rgba(167,139,250,.2); color: var(--purple); }

    .proj-status-badge {
        font-size: .68rem; font-weight: 500;
        padding: .15rem .55rem; border-radius: 100px; white-space: nowrap;
    }
    .proj-item-btns { display: flex; gap: .3rem; }

    /* ── Edit zone ── */
    .proj-edit-zone {
        display: flex; flex-direction: column; gap: .85rem;
        width: 100%; padding: 1rem 1.25rem;
    }

    /* ── Buttons ── */
    .proj-btn-primary {
        display: inline-flex; align-items: center; gap: .4rem;
        background: var(--accent); color: #fff; border: none; border-radius: 8px;
        padding: .5rem .9rem; font-family: 'DM Sans', sans-serif;
        font-size: .82rem; font-weight: 500; cursor: pointer;
        transition: background .2s, box-shadow .2s;
    }
    .proj-btn-primary:hover:not(:disabled) { background: var(--accent-h); box-shadow: 0 0 14px var(--glow); }
    .proj-btn-primary:disabled { opacity: .45; cursor: not-allowed; }
    .proj-btn-primary.proj-btn-sm { padding: .4rem .75rem; font-size: .78rem; }

    .proj-btn-ghost {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .5rem .9rem; background: transparent;
        border: 1px solid var(--border); border-radius: 8px; color: var(--t2);
        font-family: 'DM Sans', sans-serif; font-size: .82rem; cursor: pointer;
        transition: border-color .2s, color .2s;
    }
    .proj-btn-ghost:hover { border-color: var(--border-h); color: var(--t1); }
    .proj-btn-ghost.proj-btn-sm { padding: .4rem .75rem; font-size: .78rem; }

    .proj-icon-btn {
        width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid var(--border);
        background: transparent; cursor: pointer; color: var(--t3);
        transition: color .15s, border-color .15s, background .15s;
    }
    .proj-icon-btn--edit:hover   { color: var(--accent); border-color: rgba(109,93,252,.4); background: rgba(109,93,252,.08); }
    .proj-icon-btn--delete:hover { color: var(--red);    border-color: rgba(240,98,146,.4); background: var(--red-bg); }

    /* ── Spinner ── */
    .proj-spinner {
        width: 12px; height: 12px;
        border: 1.5px solid rgba(255,255,255,.3); border-top-color: #fff;
        border-radius: 50%; animation: projSpin .6s linear infinite;
        display: inline-block; flex-shrink: 0;
    }
    .proj-spinner--lg {
        width: 22px; height: 22px; border-width: 2px;
        border-color: rgba(251,146,60,.2); border-top-color: var(--orange);
    }
    @keyframes projSpin { to { transform: rotate(360deg); } }

    /* ── Empty ── */
    .proj-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 2.5rem 1.5rem; gap: .4rem; text-align: center;
    }
    .proj-empty-icon { color: var(--t3); opacity: .3; margin-bottom: .2rem; }
    .proj-empty-title { font-size: .875rem; font-weight: 500; color: var(--t2); }
    .proj-empty-sub { font-size: .78rem; color: var(--t3); }

    /* ── Transitions ── */
    .proj-slide-enter-active, .proj-slide-leave-active { transition: opacity .2s, transform .2s; }
    .proj-slide-enter-from, .proj-slide-leave-to { opacity: 0; transform: translateY(-6px); }

    /* ── Responsive ── */
    @media (max-width: 700px) {
        .proj-grid--2, .proj-grid--3 { grid-template-columns: 1fr; }
    }
</style>