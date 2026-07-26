<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Settings, X, Save, Plus, LayoutList, Columns } from 'lucide-vue-next';

    const user = computed(() => usePage().props.auth.user);

    // ── Board state ──
    const tags    = ref([]);
    const leads   = ref([]);
    const isLoading = ref(false);

    // ── DnD state ──
    const draggingLead  = ref(null);
    const dragOverTagId = ref(null);

    // ── Quick-add ──
    const quickAddTagId = ref(null);
    const quickAddForm  = ref({ nome: '', email: '', telefone: '' });
    const quickAdding   = ref(false);

    // ── Settings ──
    const showSettings   = ref(false);
    const defaultTagId   = ref(null);
    const savingSettings = ref(false);

    // ── Paleta (igual ao Dashboard.vue) ──
    const palette = {
        1: { color: '#60a5fa', bg: 'rgba(96,165,250,0.15)',  border: 'rgba(96,165,250,0.3)'  },
        2: { color: '#f59e0b', bg: 'rgba(245,158,11,0.15)',  border: 'rgba(245,158,11,0.3)'  },
        3: { color: '#34d399', bg: 'rgba(52,211,153,0.15)',  border: 'rgba(52,211,153,0.3)'  },
        4: { color: '#ef4444', bg: 'rgba(239,68,68,0.15)',   border: 'rgba(239,68,68,0.3)'   },
        5: { color: '#8b5cf6', bg: 'rgba(139,92,246,0.15)',  border: 'rgba(139,92,246,0.3)'  },
        6: { color: '#ec4899', bg: 'rgba(236,72,153,0.15)',  border: 'rgba(236,72,153,0.3)'  },
    };
    const getPalette = (id) => palette[id] || { color: '#94a3b8', bg: 'rgba(148,163,184,0.1)', border: 'rgba(148,163,184,0.25)' };

    const columnLeads = (tagId) => leads.value.filter(l => l.tag_id === tagId);

    // ── Fetch ──
    const buscarKanban = async () => {
        isLoading.value = true;
        try {
            const res = await axios.post('/api/kanban', { user_id: user.value.id });
            tags.value  = res.data.tags;
            leads.value = res.data.leads;
            defaultTagId.value = user.value.kanban_default_tag_id ?? (res.data.tags[0]?.id ?? null);
        } catch {
            tags.value  = [];
            leads.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    // ── Drag-and-drop ──
    const onDragStart = (evt, lead) => {
        draggingLead.value = lead;
        evt.dataTransfer.effectAllowed = 'move';
        evt.dataTransfer.setData('text/plain', lead.id);
    };

    const onDragEnd = () => {
        draggingLead.value  = null;
        dragOverTagId.value = null;
    };

    const onDrop = async (evt, newTagId) => {
        evt.preventDefault();
        dragOverTagId.value = null;
        const lead = draggingLead.value;
        draggingLead.value = null;
        if (!lead || lead.tag_id === newTagId) return;

        const oldTagId = lead.tag_id;
        lead.tag_id = newTagId;

        try {
            await axios.patch(`/api/usuarios/${lead.id}/tag`, { tag_id: newTagId });
        } catch {
            lead.tag_id = oldTagId;
        }
    };

    // ── Quick-add ──
    const abrirQuickAdd = (tagId) => {
        quickAddTagId.value = tagId;
        quickAddForm.value  = { nome: '', email: '', telefone: '' };
    };

    const cancelarQuickAdd = () => { quickAddTagId.value = null; };

    const salvarQuickAdd = async () => {
        if (!quickAddForm.value.nome.trim() || !quickAddForm.value.email.trim() || !quickAddForm.value.telefone.trim()) return;
        quickAdding.value = true;
        try {
            const res = await axios.post('/api/usuarios', {
                nome:     quickAddForm.value.nome.trim(),
                email:    quickAddForm.value.email.trim(),
                telefone: quickAddForm.value.telefone.trim(),
                tag_id:   quickAddTagId.value,
                user_id:  user.value.id,
            });
            quickAddTagId.value = null;
            await buscarKanban();
        } catch { /* silencioso */ }
        finally { quickAdding.value = false; }
    };

    // ── Navegação para perfil ──
    const irParaLead = (id) => {
        sessionStorage.setItem('idPerfil', id);
        router.visit(route('perfilUsuario'));
    };

    // ── Settings: coluna padrão ──
    const salvarSettings = async () => {
        savingSettings.value = true;
        try {
            await axios.patch('/api/kanban/settings', {
                user_id:        user.value.id,
                default_tag_id: defaultTagId.value,
            });
            showSettings.value = false;
        } catch { /* silencioso */ }
        finally { savingSettings.value = false; }
    };

    // ── Formatação ──
    const formatBRL = (v) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

    const formatRelativo = (ts) => {
        if (!ts) return null;
        const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 86400000);
        if (diff === 0) return 'hoje';
        if (diff === 1) return 'ontem';
        if (diff < 30)  return `há ${diff} dias`;
        if (diff < 365) return `há ${Math.floor(diff / 30)} meses`;
        return `há ${Math.floor(diff / 365)} ano(s)`;
    };

    onMounted(buscarKanban);
</script>

<template>
    <Head title="Pipeline — UserFlow" />
    <AuthenticatedLayout>
        <div class="kb-page">

            <!-- Topbar -->
            <div class="kb-topbar">
                <div class="kb-topbar-left">
                    <h1 class="kb-title">Pipeline <span class="kb-accent">de Leads</span></h1>
                    <span v-if="!isLoading" class="kb-total-badge">{{ leads.length }} lead{{ leads.length !== 1 ? 's' : '' }}</span>
                </div>

                <div class="kb-topbar-right">
                    <!-- Toggle Lista / Pipeline -->
                    <div class="view-toggle">
                        <Link :href="route('dashboard')" class="vt-btn">
                            <LayoutList :size="14" /> Lista
                        </Link>
                        <button class="vt-btn vt-btn--active">
                            <Columns :size="14" /> Pipeline
                        </button>
                    </div>

                    <!-- Engrenagem de configurações -->
                    <button @click="showSettings = !showSettings" class="kb-icon-btn" title="Configurações do Kanban">
                        <Settings :size="16" />
                    </button>
                </div>
            </div>

            <!-- Popover de configurações -->
            <Transition name="kb-fade">
                <div v-if="showSettings" class="kb-settings-overlay" @click.self="showSettings = false">
                    <div class="kb-settings-panel">
                        <div class="kb-settings-header">
                            <span class="kb-settings-title">Configurações</span>
                            <button @click="showSettings = false" class="kb-close-btn"><X :size="15" /></button>
                        </div>
                        <div class="kb-settings-body">
                            <label class="kb-settings-label">Coluna padrão para novos leads</label>
                            <select v-model="defaultTagId" class="kb-select">
                                <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.descricao }}</option>
                            </select>
                        </div>
                        <div class="kb-settings-footer">
                            <button @click="showSettings = false" class="kb-btn-ghost kb-btn-sm"><X :size="12" /> Cancelar</button>
                            <button @click="salvarSettings" class="kb-btn-primary kb-btn-sm" :disabled="savingSettings">
                                <Save :size="12" /> {{ savingSettings ? 'Salvando…' : 'Salvar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Loading -->
            <div v-if="isLoading" class="kb-loading">
                <div class="kb-spinner" />
                <span>Carregando pipeline...</span>
            </div>

            <!-- Board -->
            <div v-else class="kb-board">
                <div
                    v-for="tag in tags"
                    :key="tag.id"
                    class="kb-column"
                    :class="{ 'kb-column--over': dragOverTagId === tag.id }"
                    :style="{ '--col-color': getPalette(tag.id).color, '--col-bg': getPalette(tag.id).bg, '--col-border': getPalette(tag.id).border }"
                    @dragover.prevent="dragOverTagId = tag.id"
                    @dragleave.self="dragOverTagId = null"
                    @drop="onDrop($event, tag.id)"
                >
                    <!-- Header da coluna -->
                    <div class="kb-col-header">
                        <div class="kb-col-header-left">
                            <span class="kb-col-dot" />
                            <span class="kb-col-name">{{ tag.descricao }}</span>
                            <span class="kb-col-count">{{ columnLeads(tag.id).length }}</span>
                        </div>
                        <button
                            @click="abrirQuickAdd(tag.id)"
                            class="kb-col-add"
                            title="Adicionar lead nesta coluna"
                        >
                            <Plus :size="13" />
                        </button>
                    </div>

                    <!-- Quick-add form -->
                    <Transition name="kb-slide">
                        <div v-if="quickAddTagId === tag.id" class="kb-quick-add">
                            <input
                                v-model="quickAddForm.nome"
                                type="text"
                                placeholder="Nome *"
                                class="kb-qa-input"
                                autofocus
                                @keydown.escape="cancelarQuickAdd"
                            />
                            <input
                                v-model="quickAddForm.email"
                                type="email"
                                placeholder="E-mail *"
                                class="kb-qa-input"
                                @keydown.escape="cancelarQuickAdd"
                            />
                            <input
                                v-model="quickAddForm.telefone"
                                type="text"
                                placeholder="Telefone *"
                                class="kb-qa-input"
                                @keydown.enter="salvarQuickAdd"
                                @keydown.escape="cancelarQuickAdd"
                            />
                            <div class="kb-qa-actions">
                                <button @click="cancelarQuickAdd" class="kb-btn-ghost kb-btn-sm"><X :size="11" /></button>
                                <button
                                    @click="salvarQuickAdd"
                                    class="kb-btn-primary kb-btn-sm"
                                    :disabled="!quickAddForm.nome.trim() || !quickAddForm.email.trim() || !quickAddForm.telefone.trim() || quickAdding"
                                >
                                    <Save :size="11" /> {{ quickAdding ? '…' : 'Adicionar' }}
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Cards -->
                    <div class="kb-cards">
                        <div
                            v-for="lead in columnLeads(tag.id)"
                            :key="lead.id"
                            class="kb-card"
                            :class="{ 'kb-card--dragging': draggingLead?.id === lead.id }"
                            draggable="true"
                            @dragstart="onDragStart($event, lead)"
                            @dragend="onDragEnd"
                            @click="irParaLead(lead.id)"
                        >
                            <div class="kb-card-top">
                                <span class="kb-card-avatar">{{ lead.nome.charAt(0).toUpperCase() }}</span>
                                <div class="kb-card-info">
                                    <p class="kb-card-nome">{{ lead.nome }}</p>
                                    <p class="kb-card-email">{{ lead.email }}</p>
                                </div>
                            </div>
                            <div class="kb-card-bottom">
                                <span v-if="lead.ultimo_contato" class="kb-card-meta">
                                    {{ formatRelativo(lead.ultimo_contato) }}
                                </span>
                                <span v-if="lead.valor_projetos > 0" class="kb-card-valor">
                                    {{ formatBRL(lead.valor_projetos) }}
                                </span>
                            </div>
                        </div>

                        <!-- Drop placeholder quando a coluna está vazia -->
                        <div v-if="!columnLeads(tag.id).length && dragOverTagId === tag.id" class="kb-drop-ghost">
                            Solte aqui
                        </div>
                        <div v-else-if="!columnLeads(tag.id).length" class="kb-col-empty">
                            Nenhum lead
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .kb-page {
        --bg:      #0d1117;
        --surface: #13192a;
        --surface2: #0f1623;
        --border:  #1e2840;
        --border-h: #2a3758;
        --accent:  #6d5dfc;
        --glow:    rgba(109,93,252,0.2);
        --t1: #eaedf5;
        --t2: #8892ab;
        --t3: #4a5470;
        --inp-bg: #0b0f1a;
        font-family: 'DM Sans', sans-serif;
        min-height: 100vh;
        padding: 1.75rem 1.5rem 2rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* ── Topbar ── */
    .kb-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .kb-topbar-left  { display: flex; align-items: center; gap: 0.75rem; }
    .kb-topbar-right { display: flex; align-items: center; gap: 0.65rem; }

    .kb-title {
        font-family: 'Syne', sans-serif;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--t1);
        margin: 0;
    }
    .kb-accent { color: var(--accent); }
    .kb-total-badge {
        font-size: 0.72rem; font-weight: 600;
        padding: 0.2rem 0.65rem; border-radius: 100px;
        background: rgba(109,93,252,0.1); border: 1px solid rgba(109,93,252,0.25);
        color: var(--accent);
    }

    /* ── View Toggle ── */
    .view-toggle {
        display: flex;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        padding: 3px;
        gap: 2px;
    }
    .vt-btn {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500;
        padding: 0.35rem 0.8rem; border-radius: 7px; cursor: pointer;
        background: transparent; border: none; color: var(--t3);
        text-decoration: none;
        transition: color 0.15s, background 0.15s;
    }
    .vt-btn:hover:not(.vt-btn--active) { color: var(--t2); background: rgba(255,255,255,0.04); }
    .vt-btn--active {
        background: rgba(109,93,252,0.2);
        color: var(--t1);
        border: none;
    }

    /* ── Icon button ── */
    .kb-icon-btn {
        width: 34px; height: 34px; border-radius: 9px;
        border: 1px solid var(--border); background: var(--surface);
        cursor: pointer; color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        transition: color 0.15s, border-color 0.15s;
    }
    .kb-icon-btn:hover { color: var(--t2); border-color: var(--border-h); }

    /* ── Settings ── */
    .kb-settings-overlay {
        position: fixed; inset: 0;
        display: flex; align-items: flex-start; justify-content: flex-end;
        padding: 4.5rem 1.5rem 0;
        z-index: 500;
    }
    .kb-settings-panel {
        background: #13192a;
        border: 1px solid #1e2840;
        border-radius: 14px;
        width: 280px;
        box-shadow: 0 16px 48px rgba(0,0,0,0.4);
        overflow: hidden;
    }
    .kb-settings-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.85rem 1rem 0.75rem;
        border-bottom: 1px solid #1e2840;
    }
    .kb-settings-title { font-size: 0.85rem; font-weight: 600; color: #eaedf5; }
    .kb-close-btn {
        background: transparent; border: none; cursor: pointer;
        color: #4a5470; padding: 0; line-height: 1;
        transition: color 0.15s;
    }
    .kb-close-btn:hover { color: #8892ab; }

    .kb-settings-body { padding: 0.85rem 1rem; }
    .kb-settings-label { font-size: 0.76rem; color: #8892ab; display: block; margin-bottom: 0.45rem; }
    .kb-select {
        width: 100%; background: #0b0f1a; border: 1px solid #1e2840;
        border-radius: 8px; color: #eaedf5;
        font-family: 'DM Sans', sans-serif; font-size: 0.84rem;
        padding: 0.5rem 0.75rem; outline: none;
        transition: border-color 0.15s;
    }
    .kb-select:focus { border-color: #6d5dfc; }

    .kb-settings-footer {
        display: flex; justify-content: flex-end; gap: 0.4rem;
        padding: 0.7rem 1rem;
        border-top: 1px solid #1e2840;
    }

    /* ── Shared small buttons ── */
    .kb-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem;
        padding: 0.4rem 0.85rem; border-radius: 8px; cursor: pointer;
        background: transparent; border: 1px solid var(--border); color: var(--t3);
        transition: border-color 0.15s, color 0.15s;
    }
    .kb-btn-ghost:hover { color: var(--t2); border-color: var(--border-h); }
    .kb-btn-sm { padding: 0.3rem 0.65rem; font-size: 0.74rem; }

    .kb-btn-primary {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500;
        padding: 0.4rem 0.85rem; border-radius: 8px; cursor: pointer;
        background: var(--accent); border: none; color: #fff;
        transition: background 0.15s, box-shadow 0.15s;
    }
    .kb-btn-primary:hover:not(:disabled) { background: #7c6efd; box-shadow: 0 0 12px var(--glow); }
    .kb-btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
    .kb-btn-sm.kb-btn-primary { padding: 0.3rem 0.65rem; font-size: 0.74rem; }

    /* ── Loading ── */
    .kb-loading {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 4rem 1rem; color: var(--t3); font-size: 0.84rem;
    }
    .kb-spinner {
        width: 20px; height: 20px; flex-shrink: 0;
        border: 2px solid var(--border); border-top-color: var(--accent);
        border-radius: 50%; animation: kb-spin 0.65s linear infinite;
    }
    @keyframes kb-spin { to { transform: rotate(360deg); } }

    /* ── Board (horizontal scroll) ── */
    .kb-board {
        display: flex;
        gap: 0.9rem;
        overflow-x: auto;
        padding-bottom: 1rem;
        align-items: flex-start;
        flex: 1;
    }
    .kb-board::-webkit-scrollbar { height: 5px; }
    .kb-board::-webkit-scrollbar-track { background: transparent; }
    .kb-board::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

    /* ── Column ── */
    .kb-column {
        flex: 0 0 272px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 160px);
        transition: border-color 0.18s, background 0.18s;
    }
    .kb-column--over {
        border-color: var(--col-color) !important;
        background: var(--col-bg) !important;
    }

    /* Column header */
    .kb-col-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 0.9rem 0.65rem;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .kb-col-header-left { display: flex; align-items: center; gap: 0.5rem; }
    .kb-col-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--col-color); flex-shrink: 0;
    }
    .kb-col-name { font-size: 0.82rem; font-weight: 600; color: var(--t1); }
    .kb-col-count {
        font-size: 0.68rem; font-weight: 600;
        padding: 0.1rem 0.45rem; border-radius: 100px;
        background: var(--col-bg); color: var(--col-color);
        border: 1px solid var(--col-border);
    }
    .kb-col-add {
        width: 24px; height: 24px; border-radius: 6px;
        border: 1px solid var(--border); background: transparent;
        cursor: pointer; color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        transition: color 0.14s, border-color 0.14s, background 0.14s;
    }
    .kb-col-add:hover { color: var(--col-color); border-color: var(--col-border); background: var(--col-bg); }

    /* Quick-add */
    .kb-quick-add {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--border);
        display: flex; flex-direction: column; gap: 0.4rem;
        background: rgba(255,255,255,0.02);
        flex-shrink: 0;
    }
    .kb-qa-input {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: 7px;
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.8rem;
        padding: 0.42rem 0.65rem; outline: none; width: 100%; box-sizing: border-box;
        transition: border-color 0.15s;
    }
    .kb-qa-input:focus { border-color: var(--col-color, var(--accent)); }
    .kb-qa-input::placeholder { color: var(--t3); }
    .kb-qa-actions { display: flex; justify-content: flex-end; gap: 0.35rem; }

    /* Cards scroll area */
    .kb-cards {
        overflow-y: auto;
        padding: 0.55rem 0.65rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex: 1;
        min-height: 60px;
    }
    .kb-cards::-webkit-scrollbar { width: 4px; }
    .kb-cards::-webkit-scrollbar-track { background: transparent; }
    .kb-cards::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

    /* Card */
    .kb-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0.7rem 0.8rem;
        cursor: grab;
        transition: border-color 0.15s, box-shadow 0.15s, transform 0.12s, opacity 0.15s;
        user-select: none;
    }
    .kb-card:hover {
        border-color: var(--border-h);
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        transform: translateY(-1px);
    }
    .kb-card:active { cursor: grabbing; }
    .kb-card--dragging { opacity: 0.35; transform: scale(0.97); }

    .kb-card-top {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-bottom: 0.5rem;
    }
    .kb-card-avatar {
        width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
        background: var(--col-bg, rgba(109,93,252,0.15));
        border: 1px solid var(--col-border, rgba(109,93,252,0.3));
        color: var(--col-color, var(--accent));
        font-size: 0.75rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .kb-card-info { flex: 1; min-width: 0; }
    .kb-card-nome {
        font-size: 0.83rem; font-weight: 600; color: var(--t1);
        margin: 0 0 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .kb-card-email {
        font-size: 0.71rem; color: var(--t3);
        margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .kb-card-bottom {
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.4rem; flex-wrap: wrap;
    }
    .kb-card-meta {
        font-size: 0.68rem; color: var(--t3);
    }
    .kb-card-valor {
        font-size: 0.7rem; font-weight: 600;
        color: #34d399;
        background: rgba(52,211,153,0.1);
        border: 1px solid rgba(52,211,153,0.2);
        padding: 0.1rem 0.45rem; border-radius: 100px;
    }

    /* Empty / ghost */
    .kb-col-empty {
        font-size: 0.74rem; color: var(--t3); text-align: center;
        padding: 1.5rem 0.5rem; opacity: 0.5;
    }
    .kb-drop-ghost {
        border: 2px dashed var(--col-color);
        border-radius: 10px;
        padding: 1.5rem 0.5rem;
        text-align: center;
        font-size: 0.74rem; color: var(--col-color);
        opacity: 0.6;
        background: var(--col-bg);
    }

    /* ── Transitions ── */
    .kb-fade-enter-active, .kb-fade-leave-active { transition: opacity 0.18s; }
    .kb-fade-enter-from, .kb-fade-leave-to { opacity: 0; }

    .kb-slide-enter-active, .kb-slide-leave-active { transition: opacity 0.18s, transform 0.18s; }
    .kb-slide-enter-from, .kb-slide-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
