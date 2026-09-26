<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import axios from 'axios';
    import { Settings, X, Save, Plus, LayoutList, Columns, ArrowRightLeft } from 'lucide-vue-next';
    import ModalMotivoPerda from '@/Components/ModalMotivoPerda.vue';

    const user = computed(() => usePage().props.auth.user);

    // ── Board state ──
    const estagios = ref([]);
    const leads   = ref([]);
    const isLoading = ref(false);

    // ── Funis ──
    // O quadro mostra um funil por vez. `funilId` null significa "o padrão do
    // tenant", que é o backend quem resolve — o cliente não deve adivinhar.
    const funis      = ref([]);
    const funilAtual = ref(null);
    const funilId    = ref(null);

    // ── Perda ──
    // Um estado só para os três gatilhos que podem virar perda no quadro:
    // arrastar para uma coluna perdida, cadastrar direto nela e mover de funil
    // para um estágio perdido. Cada um informa como enviar e como desfazer; o
    // modal não conhece nenhum deles.
    const perdaPendente = ref(null);   // { titulo, enviar(perda), desfazer() }
    const perdaErro     = ref('');
    const perdaSalvando = ref(false);

    const ehPerdido = (estagio) => estagio?.tipo === 'perdido';

    const abrirPerda = (acao) => {
        perdaPendente.value = acao;
        perdaErro.value = '';
    };

    const cancelarPerda = () => {
        // Desfaz o movimento otimista: o card volta para a coluna de origem.
        perdaPendente.value?.desfazer?.();
        perdaPendente.value = null;
        perdaErro.value = '';
    };

    const confirmarPerda = async (perda) => {
        perdaSalvando.value = true;
        perdaErro.value = '';
        try {
            await perdaPendente.value.enviar(perda);
            perdaPendente.value = null;
            await buscarKanban();
        } catch (e) {
            perdaErro.value = e?.response?.data?.erros?.motivo_perda_id?.[0]
                ?? e?.response?.data?.error
                ?? 'Não foi possível registrar a perda.';
        } finally {
            perdaSalvando.value = false;
        }
    };

    // ── Mover de funil ──
    const moverAlvo       = ref(null);   // lead escolhido
    const moverFunilId    = ref(null);
    const moverEstagioId  = ref(null);
    const moverEstagios   = ref([]);
    const movendo         = ref(false);
    const moverErro       = ref('');

    // ── DnD state ──
    const draggingLead  = ref(null);
    const dragOverEstagioId = ref(null);

    // ── Quick-add ──
    const quickAddEstagioId = ref(null);
    const quickAddForm  = ref({ nome: '', email: '', telefone: '' });
    const quickAdding   = ref(false);

    // ── Settings ──
    const showSettings   = ref(false);
    const defaultEstagioId   = ref(null);
    const savingSettings = ref(false);

    // ── Paleta ──
    // Antes era um mapa fixo de id 1..6, o que só funcionava enquanto o conjunto
    // de estágios era o mesmo para todos os tenants. Com estágios criados por
    // cada empresa, a cor é um atributo do próprio estágio; ids não significam
    // nada entre tenants. O cinza é o fallback de quem ainda não escolheu cor.
    const hexParaRgba = (hex, alpha) => {
        const n = parseInt((hex || '').replace('#', ''), 16);
        if (Number.isNaN(n)) return `rgba(148,163,184,${alpha})`;
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    const getPalette = (estagio) => {
        const cor = estagio?.cor || '#94a3b8';
        return { color: cor, bg: hexParaRgba(cor, 0.15), border: hexParaRgba(cor, 0.3) };
    };

    const columnLeads = (estagioId) => leads.value.filter(l => l.estagio_id === estagioId);

    // Estágios arquivados ainda aparecem no quadro enquanto seguram leads, mas
    // não são destino válido: não recebem lead novo nem servem de coluna padrão.
    const estagiosAtivos = computed(() => estagios.value.filter(t => !t.arquivada));

    // ── Fetch ──
    const buscarKanban = async () => {
        isLoading.value = true;
        try {
            const payload = { user_id: user.value.id };
            if (funilId.value) payload.funil_id = funilId.value;

            const res = await axios.post('/api/kanban', payload);
            estagios.value = res.data.estagios;
            leads.value = res.data.leads;
            funis.value = res.data.funis ?? [];
            funilAtual.value = res.data.funil;
            funilId.value = res.data.funil?.id ?? null;

            // A coluna padrão do usuário é de um funil específico. Ao trocar de
            // funil ela não existe mais aqui, e cair no primeiro estágio ativo
            // evita um select apontando para uma opção inexistente.
            const daPreferencia = estagios.value.find(e => e.id === user.value.kanban_default_estagio_id);
            defaultEstagioId.value = daPreferencia?.id
                ?? (estagios.value.find(e => !e.arquivada)?.id ?? null);
        } catch {
            estagios.value = [];
            leads.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    const trocarFunil = async (id) => {
        funilId.value = id;
        await buscarKanban();
    };

    // ── Drag-and-drop ──
    const onDragStart = (evt, lead) => {
        draggingLead.value = lead;
        evt.dataTransfer.effectAllowed = 'move';
        evt.dataTransfer.setData('text/plain', lead.id);
    };

    const onDragEnd = () => {
        draggingLead.value  = null;
        dragOverEstagioId.value = null;
    };

    const onDragOver = (estagio) => {
        if (estagio.arquivada) return;
        dragOverEstagioId.value = estagio.id;
    };

    const onDrop = async (evt, estagio) => {
        evt.preventDefault();
        dragOverEstagioId.value = null;
        const lead = draggingLead.value;
        draggingLead.value = null;
        // Estágio arquivado é somente-saída: aceita perder leads, nunca recebê-los.
        if (!lead || estagio.arquivada || lead.estagio_id === estagio.id) return;

        const oldEstagioId = lead.estagio_id;
        lead.estagio_id = estagio.id;

        const enviar = (perda) => axios.patch(`/api/usuarios/${lead.id}/estagio`, {
            estagio_id: estagio.id,
            perda,
        });
        const desfazer = () => { lead.estagio_id = oldEstagioId; };

        // O card move na hora e o modal pergunta o motivo depois. Bloquear o
        // drop e exigir um menu seria mais fácil de implementar e trocaria o
        // gesto natural do quadro por um caminho escondido; cancelar desfaz,
        // que é o mesmo rollback já usado quando a API falha.
        if (ehPerdido(estagio)) {
            abrirPerda({ titulo: `Perder "${lead.nome}"`, enviar, desfazer });
            return;
        }

        try {
            await enviar(null);
        } catch {
            desfazer();
        }
    };

    // ── Quick-add ──
    const abrirQuickAdd = (estagioId) => {
        quickAddEstagioId.value = estagioId;
        quickAddForm.value  = { nome: '', email: '', telefone: '' };
    };

    const cancelarQuickAdd = () => { quickAddEstagioId.value = null; };

    const salvarQuickAdd = async () => {
        if (!quickAddForm.value.nome.trim() || !quickAddForm.value.email.trim() || !quickAddForm.value.telefone.trim()) return;

        const estagio = estagios.value.find(e => e.id === quickAddEstagioId.value);
        const payload = {
            nome:     quickAddForm.value.nome.trim(),
            email:    quickAddForm.value.email.trim(),
            telefone: quickAddForm.value.telefone.trim(),
            estagio_id: quickAddEstagioId.value,
            funil_id: funilAtual.value?.id ?? null,
            user_id:  user.value.id,
        };
        const enviar = (perda) => axios.post('/api/usuarios', { ...payload, perda });

        // Cadastrar um lead direto numa coluna perdida é perder: o quick-add
        // usa a coluna clicada como estágio, e ela pode ser uma delas.
        if (ehPerdido(estagio)) {
            const nome = payload.nome;
            quickAddEstagioId.value = null;
            abrirPerda({ titulo: `Cadastrar "${nome}" como perdido`, enviar, desfazer: () => {} });
            return;
        }

        quickAdding.value = true;
        try {
            await enviar(null);
            quickAddEstagioId.value = null;
            await buscarKanban();
        } catch { /* silencioso */ }
        finally { quickAdding.value = false; }
    };

    // ── Mover de funil ──
    // Transição manual e explícita: escolher o funil de destino e o estágio de
    // entrada. Arrastar o card continua movendo só dentro do quadro atual — o
    // backend recusa um estagio_id de outro funil no endpoint de arrastar.
    const abrirMover = (lead) => {
        moverAlvo.value = lead;
        moverErro.value = '';
        moverFunilId.value = funis.value.find(f => f.id !== funilAtual.value?.id)?.id ?? null;
        moverEstagioId.value = null;
        moverEstagios.value = [];
        if (moverFunilId.value) carregarEstagiosDestino();
    };

    const fecharMover = () => { moverAlvo.value = null; };

    const carregarEstagiosDestino = async () => {
        moverEstagioId.value = null;
        moverEstagios.value = [];
        if (!moverFunilId.value) return;
        try {
            const res = await axios.post('/api/estagios', { funil_id: moverFunilId.value });
            moverEstagios.value = res.data;
            moverEstagioId.value = res.data.find(e => e.tipo === 'aberto')?.id ?? res.data[0]?.id ?? null;
        } catch {
            moverErro.value = 'Não foi possível carregar os estágios do funil de destino.';
        }
    };

    const confirmarMover = async () => {
        if (!moverFunilId.value || !moverEstagioId.value) return;

        const destino = moverEstagios.value.find(e => e.id === moverEstagioId.value);
        const enviar = (perda) => axios.patch(`/api/usuarios/${moverAlvo.value.id}/funil`, {
            funil_id: moverFunilId.value,
            estagio_id: moverEstagioId.value,
            perda,
        });

        // Mover para a coluna "Perdido" de outro funil é uma perda como
        // qualquer outra — sem isto, trocar de funil seria o desvio que
        // esvazia a obrigatoriedade.
        if (ehPerdido(destino)) {
            const nome = moverAlvo.value.nome;
            fecharMover();
            abrirPerda({ titulo: `Perder "${nome}"`, enviar, desfazer: () => {} });
            return;
        }

        movendo.value = true;
        moverErro.value = '';
        try {
            await enviar(null);
            fecharMover();
            await buscarKanban();
        } catch (e) {
            moverErro.value = e?.response?.data?.error
                ?? Object.values(e?.response?.data?.erros ?? {}).flat()[0]
                ?? 'Não foi possível mover o lead.';
        } finally {
            movendo.value = false;
        }
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
                default_estagio_id: defaultEstagioId.value,
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
                    <!-- Seletor de funil -->
                    <select
                        v-if="funis.length > 1"
                        class="kb-select kb-select--funil"
                        :value="funilId"
                        @change="trocarFunil(Number($event.target.value))"
                        title="Funil exibido no quadro"
                    >
                        <option v-for="f in funis" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>

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
                            <select v-model="defaultEstagioId" class="kb-select">
                                <option v-for="estagio in estagiosAtivos" :key="estagio.id" :value="estagio.id">{{ estagio.descricao }}</option>
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

            <!-- Mover de funil -->
            <Transition name="kb-fade">
                <div v-if="moverAlvo" class="kb-settings-overlay" @click.self="fecharMover">
                    <div class="kb-settings-panel">
                        <div class="kb-settings-header">
                            <span class="kb-settings-title">Mover "{{ moverAlvo.nome }}"</span>
                            <button @click="fecharMover" class="kb-close-btn"><X :size="15" /></button>
                        </div>
                        <div class="kb-settings-body">
                            <label class="kb-settings-label">Funil de destino</label>
                            <select v-model.number="moverFunilId" class="kb-select" @change="carregarEstagiosDestino">
                                <option v-for="f in funis.filter(f => f.id !== funilAtual?.id)" :key="f.id" :value="f.id">
                                    {{ f.nome }}
                                </option>
                            </select>

                            <label class="kb-settings-label" style="margin-top: 0.85rem;">Estágio de entrada</label>
                            <select v-model.number="moverEstagioId" class="kb-select" :disabled="!moverEstagios.length">
                                <option v-for="e in moverEstagios" :key="e.id" :value="e.id">{{ e.descricao }}</option>
                            </select>

                            <p v-if="moverErro" class="kb-mover-erro">{{ moverErro }}</p>
                        </div>
                        <div class="kb-settings-footer">
                            <button @click="fecharMover" class="kb-btn-ghost kb-btn-sm"><X :size="12" /> Cancelar</button>
                            <button @click="confirmarMover" class="kb-btn-primary kb-btn-sm" :disabled="movendo || !moverEstagioId">
                                <ArrowRightLeft :size="12" /> {{ movendo ? 'Movendo…' : 'Mover' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <ModalMotivoPerda
                :aberto="!!perdaPendente"
                :titulo="perdaPendente?.titulo ?? 'Registrar perda'"
                :erro="perdaErro"
                :salvando="perdaSalvando"
                @confirmar="confirmarPerda"
                @cancelar="cancelarPerda"
            />

            <!-- Loading -->
            <div v-if="isLoading" class="kb-loading">
                <div class="kb-spinner" />
                <span>Carregando pipeline...</span>
            </div>

            <!-- Board -->
            <div v-else class="kb-board">
                <div
                    v-for="estagio in estagios"
                    :key="estagio.id"
                    class="kb-column"
                    :class="{ 'kb-column--over': dragOverEstagioId === estagio.id, 'kb-column--arquivada': estagio.arquivada }"
                    :style="{ '--col-color': getPalette(estagio).color, '--col-bg': getPalette(estagio).bg, '--col-border': getPalette(estagio).border }"
                    @dragover.prevent="onDragOver(estagio)"
                    @dragleave.self="dragOverEstagioId = null"
                    @drop="onDrop($event, estagio)"
                >
                    <!-- Header da coluna -->
                    <div class="kb-col-header">
                        <div class="kb-col-header-left">
                            <span class="kb-col-dot" />
                            <span class="kb-col-name">{{ estagio.descricao }}</span>
                            <span
                                v-if="estagio.arquivada"
                                class="kb-col-arquivada"
                                title="Estágio arquivado. Arraste os leads para outra coluna; quando esvaziar, ele some do quadro."
                            >Arquivado</span>
                            <span class="kb-col-count">{{ columnLeads(estagio.id).length }}</span>
                        </div>
                        <button
                            v-if="!estagio.arquivada"
                            @click="abrirQuickAdd(estagio.id)"
                            class="kb-col-add"
                            title="Adicionar lead nesta coluna"
                        >
                            <Plus :size="13" />
                        </button>
                    </div>

                    <!-- Quick-add form -->
                    <Transition name="kb-slide">
                        <div v-if="quickAddEstagioId === estagio.id" class="kb-quick-add">
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
                            v-for="lead in columnLeads(estagio.id)"
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
                                <button
                                    v-if="funis.length > 1"
                                    class="kb-card-mover"
                                    title="Mover para outro funil"
                                    @click.stop="abrirMover(lead)"
                                >
                                    <ArrowRightLeft :size="12" />
                                </button>
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
                        <div v-if="!columnLeads(estagio.id).length && dragOverEstagioId === estagio.id" class="kb-drop-ghost">
                            Solte aqui
                        </div>
                        <div v-else-if="!columnLeads(estagio.id).length" class="kb-col-empty">
                            Nenhum lead
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    .kb-select--funil { width: auto; min-width: 150px; }

    .kb-card-mover {
        width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
        border: 1px solid rgba(148,163,184,0.25); background: transparent;
        color: #8892ab; cursor: pointer; display: flex; align-items: center; justify-content: center;
    }
    .kb-card-mover:hover { color: #eaedf5; border-color: var(--col-color, #6d5dfc); }

    .kb-mover-erro {
        margin-top: 0.7rem; font-size: 0.78rem; color: #fca5a5;
        background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
        border-radius: 8px; padding: 0.5rem 0.65rem;
    }

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
    /* Estágio arquivado: presente só até esvaziar, e não aceita lead novo. */
    .kb-column--arquivada {
        border-style: dashed;
        opacity: 0.72;
    }
    .kb-column--arquivada .kb-col-dot { background: var(--t3); }

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
    .kb-col-arquivada {
        font-size: 0.6rem; font-weight: 600; letter-spacing: 0.04em;
        text-transform: uppercase; white-space: nowrap;
        padding: 0.1rem 0.4rem; border-radius: 4px;
        color: var(--t3); border: 1px dashed var(--border);
    }
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
