<script setup>
    import { ref, computed, onMounted } from 'vue';
    import axios from 'axios';
    import { UserPlus, MessageSquare, Paperclip, Briefcase, FileText, Upload, Tag, Clock } from 'lucide-vue-next';

    const props = defineProps({ usuarioId: { type: [Number, String], required: true } });

    const eventos    = ref([]);
    const isLoading  = ref(false);
    const filtroAtivo = ref('todos');

    const filtros = [
        { key: 'todos',           label: 'Todos' },
        { key: 'anotacao',        label: 'Anotações' },
        { key: 'arquivo',         label: 'Arquivos' },
        { key: 'projeto',         label: 'Projetos' },
        { key: 'status_alterado', label: 'Status' },
    ];

    const gruposProjeto = new Set(['projeto', 'projeto_anotacao', 'projeto_anexo']);

    const eventosFiltrados = computed(() => {
        if (filtroAtivo.value === 'todos') return eventos.value;
        if (filtroAtivo.value === 'projeto') {
            return eventos.value.filter(e => gruposProjeto.has(e.tipo));
        }
        return eventos.value.filter(e => e.tipo === filtroAtivo.value);
    });

    const formatarDataHora = (ts) => {
        if (!ts) return '';
        const d = new Date(ts);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    const tipoConfig = {
        lead_criado:      { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Lead criado' },
        anotacao:         { cor: '#60a5fa', bg: 'rgba(96,165,250,0.12)',  label: 'Anotação' },
        arquivo:          { cor: '#34d399', bg: 'rgba(52,211,153,0.12)',  label: 'Arquivo enviado' },
        projeto:          { cor: '#a78bfa', bg: 'rgba(167,139,250,0.12)', label: 'Projeto aberto' },
        projeto_anotacao: { cor: '#60a5fa', bg: 'rgba(96,165,250,0.12)',  label: 'Anotação no projeto' },
        projeto_anexo:    { cor: '#34d399', bg: 'rgba(52,211,153,0.12)',  label: 'Anexo no projeto' },
        status_alterado:  { cor: '#f59e0b', bg: 'rgba(245,158,11,0.12)',  label: 'Status alterado' },
    };

    const getConfig = (tipo) => tipoConfig[tipo] || tipoConfig.lead_criado;

    const getTitulo = (evento) => {
        switch (evento.tipo) {
            case 'lead_criado':      return 'Lead cadastrado no sistema';
            case 'anotacao':         return 'Anotação registrada';
            case 'arquivo':          return evento.nome || 'Arquivo enviado';
            case 'projeto':          return evento.nome || 'Projeto aberto';
            case 'projeto_anotacao': return `Anotação em "${evento.projeto_nome}"`;
            case 'projeto_anexo':    return `Arquivo em "${evento.projeto_nome}"`;
            case 'status_alterado':  return `Status: ${evento.tag_anterior} → ${evento.tag_novo}`;
            default: return '';
        }
    };

    const getDescricao = (evento) => {
        if (evento.tipo === 'anotacao' || evento.tipo === 'projeto_anotacao') return evento.descricao;
        if (evento.tipo === 'arquivo' || evento.tipo === 'projeto_anexo') return null;
        return null;
    };

    const buscarTimeline = async () => {
        isLoading.value = true;
        try {
            const res = await axios.get(`/api/timeline/${props.usuarioId}`);
            eventos.value = res.data;
        } catch {
            eventos.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(buscarTimeline);
</script>

<template>
    <div class="tl-wrap">

        <!-- Filtros -->
        <div class="tl-filters">
            <button
                v-for="f in filtros"
                :key="f.key"
                class="tl-chip"
                :class="{ 'tl-chip--active': filtroAtivo === f.key }"
                @click="filtroAtivo = f.key"
            >
                {{ f.label }}
                <span v-if="f.key === 'todos'" class="tl-chip-count">{{ eventos.length }}</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="tl-loading">
            <div class="tl-spinner" />
            <span>Carregando timeline...</span>
        </div>

        <!-- Vazio -->
        <div v-else-if="eventosFiltrados.length === 0" class="tl-empty">
            <Clock :size="28" />
            <p class="tl-empty-title">Nenhum evento registrado</p>
            <p class="tl-empty-sub">Os eventos aparecerão aqui conforme o lead evoluir.</p>
        </div>

        <!-- Lista -->
        <div v-else class="tl-list">
            <div
                v-for="(evento, i) in eventosFiltrados"
                :key="i"
                class="tl-item"
                :style="{ '--dot-color': getConfig(evento.tipo).cor, animationDelay: `${i * 0.03}s` }"
            >
                <!-- Dot + linha -->
                <div class="tl-track">
                    <div
                        class="tl-dot"
                        :style="{ background: getConfig(evento.tipo).bg, color: getConfig(evento.tipo).cor, border: `1px solid ${getConfig(evento.tipo).cor}40` }"
                    >
                        <UserPlus      v-if="evento.tipo === 'lead_criado'"      :size="11" />
                        <MessageSquare v-else-if="evento.tipo === 'anotacao'"    :size="11" />
                        <Paperclip     v-else-if="evento.tipo === 'arquivo'"     :size="11" />
                        <Briefcase     v-else-if="evento.tipo === 'projeto'"     :size="11" />
                        <FileText      v-else-if="evento.tipo === 'projeto_anotacao'" :size="11" />
                        <Upload        v-else-if="evento.tipo === 'projeto_anexo'"    :size="11" />
                        <Tag           v-else-if="evento.tipo === 'status_alterado'"  :size="11" />
                    </div>
                    <div class="tl-line" />
                </div>

                <!-- Card -->
                <div class="tl-card">
                    <div class="tl-card-top">
                        <span
                            class="tl-badge"
                            :style="{ background: getConfig(evento.tipo).bg, color: getConfig(evento.tipo).cor }"
                        >
                            {{ getConfig(evento.tipo).label }}
                        </span>
                        <span class="tl-date">{{ formatarDataHora(evento.data) }}</span>
                    </div>
                    <p class="tl-titulo">{{ getTitulo(evento) }}</p>
                    <p v-if="getDescricao(evento)" class="tl-descricao">{{ getDescricao(evento) }}</p>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
    .tl-wrap {
        --bg:      #0d1117;
        --surface: #13192a;
        --border:  #1e2840;
        --t1:      #eaedf5;
        --t2:      #8892ab;
        --t3:      #4a5470;
        font-family: 'DM Sans', sans-serif;
    }

    /* ── Filtros ── */
    .tl-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-bottom: 1.25rem;
    }
    .tl-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        border-radius: 100px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.78rem;
        font-weight: 500;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--t3);
        cursor: pointer;
        transition: color 0.18s, border-color 0.18s, background 0.18s;
    }
    .tl-chip:hover { color: var(--t2); border-color: #2a3758; }
    .tl-chip--active {
        background: rgba(109, 93, 252, 0.13);
        border-color: rgba(109, 93, 252, 0.35);
        color: #a78bfa;
    }
    .tl-chip-count {
        font-size: 0.68rem;
        background: rgba(255,255,255,0.07);
        border-radius: 100px;
        padding: 0 0.4rem;
        line-height: 1.5;
    }

    /* ── Loading ── */
    .tl-loading {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 2.5rem 1rem;
        color: var(--t3);
        font-size: 0.82rem;
    }
    .tl-spinner {
        width: 18px; height: 18px;
        border: 2px solid var(--border);
        border-top-color: #a78bfa;
        border-radius: 50%;
        animation: tl-spin 0.65s linear infinite;
        flex-shrink: 0;
    }
    @keyframes tl-spin { to { transform: rotate(360deg); } }

    /* ── Vazio ── */
    .tl-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 1.5rem;
        gap: 0.4rem;
        text-align: center;
        color: var(--t3);
        opacity: 0.5;
    }
    .tl-empty-title { font-size: 0.875rem; font-weight: 500; color: var(--t2); margin: 0.35rem 0 0; opacity: 1; }
    .tl-empty-sub   { font-size: 0.78rem; color: var(--t3); margin: 0; opacity: 1; }

    /* ── Lista ── */
    .tl-list {
        display: flex;
        flex-direction: column;
        padding: 0.25rem 0;
    }

    .tl-item {
        display: flex;
        gap: 0.85rem;
        animation: tl-fadein 0.3s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes tl-fadein { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

    /* Track (dot + linha) */
    .tl-track {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
        padding-top: 0.1rem;
    }
    .tl-dot {
        width: 28px; height: 28px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        z-index: 1;
    }
    .tl-line {
        width: 1px;
        flex: 1;
        background: var(--border);
        margin-top: 4px;
        min-height: 18px;
    }
    .tl-item:last-child .tl-line { display: none; }

    /* Card */
    .tl-card {
        flex: 1;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        margin-bottom: 0.65rem;
        transition: border-color 0.15s;
        min-width: 0;
    }
    .tl-card:hover { border-color: #2a3758; }
    .tl-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.35rem;
        flex-wrap: wrap;
    }
    .tl-badge {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.15rem 0.55rem;
        border-radius: 100px;
    }
    .tl-date {
        font-size: 0.72rem;
        color: var(--t3);
        white-space: nowrap;
    }
    .tl-titulo {
        font-size: 0.845rem;
        font-weight: 500;
        color: var(--t1);
        margin: 0 0 0.15rem;
        word-break: break-word;
    }
    .tl-descricao {
        font-size: 0.8rem;
        color: var(--t2);
        line-height: 1.55;
        margin: 0;
        word-break: break-word;
    }

    /* ── Responsive ── */
    @media (max-width: 600px) {
        .tl-card { padding: 0.6rem 0.8rem; }
        .tl-dot  { width: 24px; height: 24px; }
    }
</style>
