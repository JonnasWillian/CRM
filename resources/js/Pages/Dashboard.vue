<script setup>
    import { ref, onMounted, computed, watch } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import axios from 'axios';
    import { vMaska } from 'maska/vue';
    import { formatarTelefone, MASCARA_TELEFONE } from '@/utils/telefone';

    const usuarios = ref([]);
    const isModalOpen = ref(false);
    const isLoading = ref(false);
    const search = ref('');
    const user = computed(() => usePage().props.auth.user);
    const limites = usePage().props.limites;

    const form = ref({
        nome: '',
        email: '',
        telefone: '',
        descricao: '',
    });

    const formErrors = ref({});

    const metricas = ref(null);
    const isLoadingMetricas = ref(false);

    // A cor é atributo do estágio, não função do id. O mapa fixo de 1..6 que
    // existia aqui só funcionava porque todo tenant tinha os mesmos seis
    // estágios; com funis customizáveis, id 3 é "Concluído" numa empresa e
    // "Reunião marcada" na outra.
    const hexParaRgba = (hex, alpha) => {
        const n = parseInt((hex || '').replace('#', ''), 16);
        if (Number.isNaN(n)) return `rgba(148,163,184,${alpha})`;
        return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    const corDoEstagio = (id) => estagios.value.find(e => e.id === id)?.cor || '#94a3b8';

    const getEstagioPalette = (id) => {
        const cor = corDoEstagio(id);
        return { color: cor, bg: hexParaRgba(cor, 0.18) };
    };

    const formatBRL = (v) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

    const maxLeadsEstagio = computed(() =>
        Math.max(1, ...(metricas.value?.leads_por_estagio?.map(t => t.total) ?? [1]))
    );

    const buscarMetricas = async () => {
        isLoadingMetricas.value = true;
        try {
            const res = await axios.get('/api/metricas');
            metricas.value = res.data;
        } catch {  }
        finally { isLoadingMetricas.value = false; }
    };

    const tarefasPendentes = ref({ hoje: [], atrasadas: [] });

    const buscarTarefasPendentes = async () => {
        try {
            const res = await axios.get('/api/tarefasPendentes');
            tarefasPendentes.value = res.data;
        } catch {  }
    };

    const estiloDoEstagio = (id) => {
        const cor = corDoEstagio(id);
        return { color: cor, background: hexParaRgba(cor, 0.12) };
    };

    const estagios             = ref([]);
    const filterEstagios       = ref([]);
    const filterStatus     = ref('todos');
    const filterDatePreset = ref('todos');
    const filterDateFrom   = ref('');
    const filterDateTo     = ref('');

    const buscarEstagios = async () => {
        try {
            const res = await axios.get('/api/estagios');
            estagios.value = res.data;
        } catch { /* silencioso */ }
    };

    const dateFromComputed = computed(() => {
        if (filterDatePreset.value === '7d')  return new Date(Date.now() - 7 * 86400000);
        if (filterDatePreset.value === '30d') return new Date(Date.now() - 30 * 86400000);
        if (filterDatePreset.value === 'custom' && filterDateFrom.value) return new Date(filterDateFrom.value + 'T00:00:00');
        return null;
    });

    const dateToComputed = computed(() => {
        if (filterDatePreset.value === 'custom' && filterDateTo.value) return new Date(filterDateTo.value + 'T23:59:59');
        return null;
    });

    const activeFiltersCount = computed(() =>
        (filterEstagios.value.length > 0 ? 1 : 0) +
        (filterStatus.value !== 'todos' ? 1 : 0) +
        (filterDatePreset.value !== 'todos' ? 1 : 0)
    );

    const clearFilters = () => {
        filterEstagios.value       = [];
        filterStatus.value     = 'todos';
        filterDatePreset.value = 'todos';
        filterDateFrom.value   = '';
        filterDateTo.value     = '';
    };

    const toggleFilterEstagio = (id) => {
        const idx = filterEstagios.value.indexOf(id);
        if (idx === -1) filterEstagios.value.push(id);
        else filterEstagios.value.splice(idx, 1);
    };

    const paginacao = ref({ pagina: 1, ultimaPagina: 1, total: 0 });

    // Instante completo, não só a data: as datas acima já são início/fim do
    // dia LOCAL, e cortar para YYYY-MM-DD (em UTC) deslocava o período em um
    // dia no fuso do Brasil. O servidor usa o instante como veio.
    const dataISO = (d) => d ? d.toISOString() : undefined;

    // Os filtros que antes rodavam no navegador sobre a carteira inteira agora
    // viram parâmetros da consulta (ListagemDeLeads, no servidor).
    const parametrosDaLista = (pagina) => ({
        page: pagina,
        per_page: 25,
        busca: search.value?.trim() || undefined,
        estagios: filterEstagios.value.length ? filterEstagios.value : undefined,
        de: dataISO(dateFromComputed.value),
        ate: dataISO(dateToComputed.value),
        status: filterStatus.value !== 'todos' ? filterStatus.value : undefined,
    });

    const buscarUsuarios = async (pagina = 1) => {
        isLoading.value = true;
        try {
            const { data } = await axios.get('/api/leads', { params: parametrosDaLista(pagina) });
            usuarios.value = data.data.map(usuario => ({
                ...usuario,
                telefone: usuario.telefone ?? ''
            }));
            paginacao.value = { pagina: data.current_page, ultimaPagina: data.last_page, total: data.total };
        } catch (error) {
            console.error('Erro ao buscar leads:', error);
        } finally {
            isLoading.value = false;
        }
    };

    // Nenhum lead na base inteira (sem filtro/busca ativos) é diferente de
    // nenhum resultado PARA os filtros escolhidos — a mensagem e o botão de
    // atalho mudam conforme o caso.
    const nenhumLeadCadastrado = computed(() =>
        paginacao.value.total === 0 && activeFiltersCount.value === 0 && !search.value
    );

    // Filtro mudou: volta para a primeira página. Busca por texto espera o
    // usuário parar de digitar (300 ms) para não disparar uma consulta por tecla.
    let atrasoDaBusca = null;
    watch(search, () => {
        clearTimeout(atrasoDaBusca);
        atrasoDaBusca = setTimeout(() => buscarUsuarios(1), 300);
    });
    watch([filterEstagios, filterStatus, filterDatePreset, filterDateFrom, filterDateTo], () => buscarUsuarios(1), { deep: true });

    const addUsuario = async () => {
        formErrors.value = {};

        const payload = { ...form.value };

        // O funil e o estágio de entrada são resolvidos pelo backend, a partir
        // do funil padrão do tenant. O `estagio_id = 1` que ficava aqui era um
        // id chutado — válido só enquanto todo tenant tinha os mesmos estágios.

        try {
            await axios.post('/api/usuarios', payload);
            await buscarUsuarios();
            closeModal();
            search.value = '';
        } catch (error) {
            const data = error?.response?.data;
            const erros = data?.errors ?? data?.erros ?? {};
            if (Object.keys(erros).length) {
                formErrors.value = erros;
            } else {
                formErrors.value = { _geral: ['Erro ao cadastrar lead. Verifique os dados e tente novamente.'] };
            }
        }
    };

    const openModal = () => {
        isModalOpen.value = true;
    };

    const closeModal = () => {
        form.value = { nome: '', email: '', telefone: '', descricao: '' };
        formErrors.value = {};
        isModalOpen.value = false;
    };

    const usuarioPerfil = (id) => {
        router.visit(route('leads.show', id));
    };

    const getInitials = (nome) =>
        nome?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?';

    onMounted(() => {
        buscarUsuarios();
        buscarMetricas();
        buscarTarefasPendentes();
        buscarEstagios();
    });
</script>

<template>
    <Head title="Dashboard — UserFlow" />

    <AuthenticatedLayout>
        <div class="page">
            <div class="dot-grid" aria-hidden="true" />

            <div class="p-10">

                <div class="topbar">
                    <div>
                        <p class="topbar-greeting">Olá, {{ user.name.split(' ')[0] }}</p>
                        <h1 class="topbar-title">Lista de <span class="accent">Leads</span></h1>
                    </div>
                    <div class="view-toggle">
                        <button class="vt-btn vt-btn--active">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Lista
                        </button>
                        <Link :href="route('kanban')" class="vt-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                            Pipeline
                        </Link>
                    </div>
                    <button @click="openModal" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Novo Lead
                    </button>
                </div>

                <!-- ── Métricas ── -->
                <div class="metrics-section">

                    <!-- Skeleton enquanto carrega -->
                    <template v-if="isLoadingMetricas">
                        <div class="summary-row">
                            <div v-for="i in 4" :key="i" class="summary-card skel-card" />
                        </div>
                        <div class="metrics-row2">
                            <div class="skel-block skel-chart" />
                            <div class="skel-block skel-donut" />
                        </div>
                    </template>

                    <template v-else-if="metricas">
                        <!-- Row 1: 4 stat cards -->
                        <div class="summary-row">

                            <!-- Leads Ativos -->
                            <div class="summary-card">
                                <div class="summary-icon summary-icon--green">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div>
                                    <p class="summary-value num is-ok">{{ metricas.leads_ativos }}</p>
                                    <p class="summary-label">Leads Ativos</p>
                                    <p class="summary-sub">{{ metricas.leads_arquivados }} arquivados</p>
                                </div>
                            </div>

                            <!-- Novos 30 dias -->
                            <div class="summary-card">
                                <div class="summary-icon summary-icon--blue">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div>
                                    <p class="summary-value num is-info">{{ metricas.leads_30_dias }}</p>
                                    <p class="summary-label">Novos (30 dias)</p>
                                    <p class="summary-sub">de {{ metricas.total_leads }} total</p>
                                </div>
                            </div>

                            <!-- Valor em aberto -->
                            <div class="summary-card">
                                <div class="summary-icon summary-icon--orange">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <div class="summary-text-overflow">
                                    <p class="summary-value num summary-value--sm is-warn">{{ formatBRL(metricas.valor_projetos_abertos) }}</p>
                                    <p class="summary-label">Em aberto</p>
                                    <p class="summary-sub">projetos ativos</p>
                                </div>
                            </div>

                            <!-- Fechado no mês -->
                            <div class="summary-card">
                                <div class="summary-icon" style="background:rgba(167,139,250,0.15);color:#a78bfa">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="summary-text-overflow">
                                    <p class="summary-value num summary-value--sm is-cat">{{ formatBRL(metricas.valor_fechado_mes) }}</p>
                                    <p class="summary-label">Fechado no mês</p>
                                    <p class="summary-sub">status concluído</p>
                                </div>
                            </div>

                        </div>

                        <!-- Row 2: gráfico de barras + taxa de conversão -->
                        <div class="metrics-row2">

                            <!-- Leads por estágio -->
                            <div class="metric-card chart-card">
                                <p class="metric-card-title">Leads por Estágio</p>
                                <div v-if="metricas.leads_por_estagio.length" class="bar-list">
                                    <div v-for="estagio in metricas.leads_por_estagio" :key="estagio.id" class="bar-row">
                                        <span class="bar-label">{{ estagio.descricao }}</span>
                                        <div class="bar-track">
                                            <div
                                                class="bar-fill"
                                                :style="{
                                                    width: (estagio.total / maxLeadsEstagio * 100) + '%',
                                                    background: getEstagioPalette(estagio.id).color,
                                                    boxShadow: `0 0 8px ${getEstagioPalette(estagio.id).color}55`
                                                }"
                                            />
                                        </div>
                                        <span class="bar-count num" :style="{ color: getEstagioPalette(estagio.id).color }">{{ estagio.total }}</span>
                                    </div>
                                </div>
                                <p v-else class="metric-empty">Nenhum lead com estágio definido.</p>
                            </div>

                            <!-- Taxa de conversão -->
                            <div class="metric-card conv-card">
                                <p class="metric-card-title">Taxa de Conversão</p>
                                <div class="donut-wrap">
                                    <svg viewBox="0 0 100 100" class="donut-svg" aria-hidden="true">
                                        <!-- Track -->
                                        <circle cx="50" cy="50" r="35" fill="none" stroke="#1e2840" stroke-width="8" />
                                        <!-- Fill -->
                                        <circle
                                            cx="50" cy="50" r="35" fill="none"
                                            :stroke="metricas.taxa_conversao >= 50 ? '#3ecf8e' : '#6d5dfc'"
                                            stroke-width="8"
                                            stroke-linecap="round"
                                            :stroke-dasharray="`${metricas.taxa_conversao * 2.199} 220`"
                                            transform="rotate(-90 50 50)"
                                            style="transition: stroke-dasharray 0.8s ease"
                                        />
                                        <text x="50" y="47" text-anchor="middle" font-size="17" font-weight="700" font-family="Syne,sans-serif" fill="#eaedf5">{{ metricas.taxa_conversao }}%</text>
                                        <text x="50" y="60" text-anchor="middle" font-size="6.5" fill="#4a5470">conversão</text>
                                    </svg>
                                </div>
                                <p class="conv-legend">
                                    <span class="conv-highlight">{{ metricas.total_com_projeto }}</span>
                                    de
                                    <span class="conv-highlight">{{ metricas.total_leads }}</span>
                                    leads têm projeto
                                </p>
                            </div>

                        </div>
                    </template>

                    <!-- Fallback se metricas falhar -->
                    <template v-else>
                        <div class="summary-row">
                            <div class="summary-card summary-card--accent">
                                <div class="summary-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </div>
                                <div>
                                    <p class="summary-value num">{{ paginacao.total }}</p>
                                    <p class="summary-label">Total de Leads</p>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>

                <!-- ── Widget de Tarefas ── -->
                <div v-if="tarefasPendentes.atrasadas.length || tarefasPendentes.hoje.length" class="tasks-widget">

                    <div v-if="tarefasPendentes.atrasadas.length" class="tasks-alert tasks-alert--danger">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span class="tasks-alert-label">{{ tarefasPendentes.atrasadas.length }} tarefa{{ tarefasPendentes.atrasadas.length !== 1 ? 's' : '' }} em atraso</span>
                        <div class="tasks-chip-list">
                            <span
                                v-for="t in tarefasPendentes.atrasadas.slice(0, 3)"
                                :key="t.id"
                                class="task-chip task-chip--danger"
                                @click="usuarioPerfil(t.usuario_id)"
                            >
                                {{ t.lead.nome }} · {{ t.titulo }}
                            </span>
                            <span v-if="tarefasPendentes.atrasadas.length > 3" class="task-chip-more">
                                +{{ tarefasPendentes.atrasadas.length - 3 }} mais
                            </span>
                        </div>
                    </div>

                    <div v-if="tarefasPendentes.hoje.length" class="tasks-alert tasks-alert--today">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="tasks-alert-label">{{ tarefasPendentes.hoje.length }} tarefa{{ tarefasPendentes.hoje.length !== 1 ? 's' : '' }} para hoje</span>
                        <div class="tasks-chip-list">
                            <span
                                v-for="t in tarefasPendentes.hoje.slice(0, 3)"
                                :key="t.id"
                                class="task-chip task-chip--today"
                                @click="usuarioPerfil(t.usuario_id)"
                            >
                                {{ t.lead.nome }} · {{ t.titulo }}
                            </span>
                            <span v-if="tarefasPendentes.hoje.length > 3" class="task-chip-more">
                                +{{ tarefasPendentes.hoje.length - 3 }} mais
                            </span>
                        </div>
                    </div>

                </div>

                <div class="leads-panel">
                    <div class="panel-header">
                        <div class="panel-title-row">
                            <h2 class="panel-title">Leads cadastrados</h2>
                            <span class="badge">{{ paginacao.total }}</span>
                        </div>

                        <div class="search-box">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar por nome, e-mail ou telefone..."
                                class="search-input"
                            />
                        </div>

                        <!-- ── Barra de Filtros ── -->
                        <div class="filter-bar" v-if="!isLoading">

                            <!-- Linha 1: Estágio -->
                            <div class="fb-row">
                                <span class="fb-label">Estágio</span>
                                <div class="fb-chips">
                                    <button
                                        v-for="estagio in estagios"
                                        :key="estagio.id"
                                        class="fb-chip fb-chip--estagio"
                                        :class="{ 'fb-chip--active': filterEstagios.includes(estagio.id) }"
                                        :style="filterEstagios.includes(estagio.id)
                                            ? { background: getEstagioPalette(estagio.id).bg, borderColor: getEstagioPalette(estagio.id).color, color: getEstagioPalette(estagio.id).color }
                                            : {}"
                                        @click="toggleFilterEstagio(estagio.id)"
                                    >{{ estagio.descricao }}</button>
                                </div>
                            </div>

                            <!-- Linha 2: Período -->
                            <div class="fb-row">
                                <span class="fb-label">Período</span>
                                <div class="fb-chips">
                                    <button v-for="p in [
                                        { key: 'todos',  label: 'Todos' },
                                        { key: '7d',     label: 'Últimos 7d' },
                                        { key: '30d',    label: 'Últimos 30d' },
                                        { key: 'custom', label: 'Personalizado' },
                                    ]" :key="p.key"
                                        class="fb-chip"
                                        :class="{ 'fb-chip--active': filterDatePreset === p.key }"
                                        @click="filterDatePreset = p.key"
                                    >{{ p.label }}</button>
                                </div>
                                <Transition name="fb-slide">
                                    <div v-if="filterDatePreset === 'custom'" class="fb-date-range">
                                        <input v-model="filterDateFrom" type="date" class="fb-date-input" />
                                        <span class="fb-date-sep">→</span>
                                        <input v-model="filterDateTo" type="date" class="fb-date-input" />
                                    </div>
                                </Transition>
                            </div>

                            <!-- Linha 3: Status de projeto -->
                            <div class="fb-row">
                                <span class="fb-label">Status</span>
                                <div class="fb-chips">
                                    <button v-for="s in [
                                        { key: 'todos',       label: 'Todos' },
                                        { key: 'aberto',      label: 'Com projeto aberto' },
                                        { key: 'sem_projeto', label: 'Sem projeto' },
                                        { key: 'arquivado',   label: 'Arquivado' },
                                    ]" :key="s.key"
                                        class="fb-chip"
                                        :class="{ 'fb-chip--active': filterStatus === s.key }"
                                        @click="filterStatus = s.key"
                                    >{{ s.label }}</button>
                                </div>
                            </div>

                            <!-- Limpar filtros -->
                            <Transition name="fb-slide">
                                <div v-if="activeFiltersCount > 0" class="fb-clear-row">
                                    <button @click="clearFilters" class="fb-clear-btn">
                                        ✕ Limpar filtros ({{ activeFiltersCount }})
                                    </button>
                                    <span class="fb-result-count">{{ paginacao.total }} resultado{{ paginacao.total !== 1 ? 's' : '' }}</span>
                                </div>
                            </Transition>

                        </div>
                    </div>

                    <div class="table-head">
                        <span class="col-lead">Lead</span>
                        <span class="col-contact">Contato</span>
                        <span class="col-desc">Descrição</span>
                        <span class="col-action">Ação</span>
                    </div>

                    <div v-if="isLoading" class="state-center">
                        <div class="spinner" />
                        <p class="state-text">Carregando leads...</p>
                    </div>

                    <div v-else-if="paginacao.total === 0" class="state-center">
                        <div class="empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <p class="state-title">{{ nenhumLeadCadastrado ? 'Nenhum lead cadastrado' : 'Nenhum resultado encontrado' }}</p>
                        <p class="state-sub">{{ nenhumLeadCadastrado ? 'Clique em "Novo Lead" para começar' : 'Tente buscar por outro termo' }}</p>
                        <button v-if="nenhumLeadCadastrado" @click="openModal" class="btn-primary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            Adicionar primeiro lead
                        </button>
                    </div>

                    <div v-else class="table-body">
                        <div
                            v-for="(usuario, i) in usuarios"
                            :key="usuario.id"
                            class="table-row"
                            :style="{ '--i': Math.min(i, 8) }"
                            @click="usuarioPerfil(usuario.id)"
                        >
                            <div class="col-lead">
                                <div class="lead-avatar">{{ getInitials(usuario.nome) }}</div>
                                <div class="lead-name-block">
                                    <p class="lead-name">{{ usuario.nome }}</p>
                                    <p class="lead-email">{{ usuario.email }}</p>
                                    <p class="lead-email estagio-badge" :style="estiloDoEstagio(usuario?.estagio?.id)">{{ usuario?.estagio?.descricao }}</p>
                                </div>
                            </div>

                            <div class="col-contact">
                                <span class="phone-tag">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    {{ formatarTelefone(usuario.telefone) || '—' }}
                                </span>
                            </div>

                            <div class="col-desc">
                                <p class="desc-text">{{ usuario.descricao || '—' }}</p>
                            </div>

                            <div class="col-action">
                                <button class="row-action" @click.stop="usuarioPerfil(usuario.id)" title="Ver perfil">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="paginacao.ultimaPagina > 1" class="db-paginacao">
                        <button class="btn-ghost" :disabled="paginacao.pagina <= 1 || isLoading" @click="buscarUsuarios(paginacao.pagina - 1)">Anterior</button>
                        <span>Página {{ paginacao.pagina }} de {{ paginacao.ultimaPagina }} · {{ paginacao.total }} leads</span>
                        <button class="btn-ghost" :disabled="paginacao.pagina >= paginacao.ultimaPagina || isLoading" @click="buscarUsuarios(paginacao.pagina + 1)">Próxima</button>
                    </div>
                </div>

            </div>

            <Transition name="modal">
                <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                    <div class="modal">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title">Novo Lead</h2>
                                <p class="modal-sub">Preencha os dados do lead</p>
                            </div>
                            <button @click="closeModal" class="modal-close">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="addUsuario" class="modal-form">
                            <div class="field">
                                <label class="field-label">Nome completo*</label>
                                <input
                                    type="text"
                                    v-model="form.nome"
                                    placeholder="Ex: João Silva"
                                    class="field-input"
                                    :class="{ 'field-input--error': formErrors.nome }"
                                    minlength="5"
                                    required
                                />
                                <p v-if="formErrors.nome" class="field-error">{{ formErrors.nome[0] }}</p>
                            </div>

                            <div class="field">
                                <label class="field-label">E-mail*</label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    placeholder="email@exemplo.com"
                                    class="field-input"
                                    :class="{ 'field-input--error': formErrors.email }"
                                    required
                                />
                                <p v-if="formErrors.email" class="field-error">{{ formErrors.email[0] }}</p>
                            </div>

                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Telefone</label>
                                    <input
                                        type="tel"
                                        v-model="form.telefone"
                                        placeholder="(00) 00000-0000"
                                        v-maska
                                        :data-maska="MASCARA_TELEFONE"
                                        class="field-input"
                                        :class="{ 'field-input--error': formErrors.telefone }"
                                    />
                                    <p v-if="formErrors.telefone" class="field-error">{{ formErrors.telefone[0] }}</p>
                                </div>
                                <div class="field">
                                    <label class="field-label">Descrição</label>
                                    <input
                                        type="text"
                                        v-model="form.descricao"
                                        placeholder="Observação rápida"
                                        class="field-input"
                                        :maxlength="limites.descricao"
                                        :class="{ 'field-input--error': formErrors.descricao }"
                                    />
                                    <p v-if="formErrors.descricao" class="field-error">{{ formErrors.descricao[0] }}</p>
                                </div>
                            </div>

                            <p v-if="formErrors._geral" class="field-error field-error--geral">{{ formErrors._geral[0] }}</p>

                            <div class="modal-footer">
                                <button type="button" class="btn-ghost" @click="closeModal">Cancelar</button>
                                <button type="submit" class="btn-primary">Salvar Lead</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap');

    .page {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }

    /* Cores dos estágios */
    .estagio-badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 2px 8px;
        border-radius: var(--r-1);
        display: inline-block;
        margin-top: 4px;
        width: fit-content;
    }

    /* As cores por estágio saíram daqui: elas eram sete classes fixas, uma por
       id do conjunto antigo. Agora a cor vem do próprio estágio (estagios.cor)
       e é aplicada inline por estiloDoEstagio(). */

    .dot-grid {
        position: fixed;
        inset: 0;
        background-image: radial-gradient(circle, var(--line) 1px, transparent 1px);
        background-size: 30px 30px;
        opacity: 0.45;
        pointer-events: none;
        z-index: 0;
    }

    .content {
        position: relative;
        z-index: 1;
        padding: 2.5rem 2.5rem 3rem;
        width: 100%;
    }

    .topbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 2rem;
    }

    .view-toggle {
        display: flex;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-2);
        overflow: hidden;
        padding: 3px;
        gap: 2px;
    }
    .vt-btn {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500;
        padding: 0.35rem 0.8rem; border-radius: var(--r-1); cursor: pointer;
        background: transparent; border: none; color: var(--t3);
        text-decoration: none;
        transition: color var(--d-1), background 0.15s;
    }
    .vt-btn:hover:not(.vt-btn--active) { color: var(--t2); background: rgba(255,255,255,0.04); }
    .vt-btn--active { background: rgba(109,93,252,0.2); color: var(--t1); }

    .topbar-greeting {
        font-size: 0.82rem;
        color: var(--t3);
        margin-bottom: 0.2rem;
    }

    .topbar-title {
        font-family: 'Syne', sans-serif;
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--t1);
        letter-spacing: -0.5px;
    }

    .accent { color: var(--accent); }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: var(--r-2);
        padding: 0.65rem 1.2rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: background var(--d-1), box-shadow 0.2s, transform 0.1s;
        white-space: nowrap;
    }

    .btn-primary svg { width: 15px; height: 15px; }

    .btn-primary:hover { background: var(--accent-h); box-shadow: 0 0 20px var(--glow); transform: translateY(-1px); }

    .btn-primary:active { transform: translateY(0); }

    .btn-sm { font-size: 0.82rem; padding: 0.55rem 1rem; }

    .btn-ghost {
        padding: 0.65rem 1.2rem;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: var(--r-2);
        color: var(--t2);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        cursor: pointer;
        transition: border-color var(--d-1), color 0.2s;
    }

    .btn-ghost:hover { border-color: var(--t2); color: var(--t1); }

    .summary-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-3);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: border-color var(--d-1), transform 0.15s;
    }

    .summary-card:hover { border-color: rgba(109,93,252,0.3); transform: translateY(-2px); }

    .summary-card--accent { border-color: rgba(109,93,252,0.3); }

    .summary-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--r-2);
        background: rgba(109, 93, 252, 0.15);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .summary-icon svg { width: 18px; height: 18px; }

    .summary-icon--green { background: rgba(62,207,142,0.12); color: var(--green); }

    .summary-icon--blue  { background: rgba(56,189,248,0.12); color: var(--blue); }

    .summary-icon--orange { background: rgba(251,146,60,0.12); color: var(--orange); }

    .summary-value {
        font-family: 'Syne', sans-serif;
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--t1);
        line-height: 1;
        margin-bottom: 0.2rem;
    }

    .summary-label { font-size: 0.72rem; color: var(--t3); }

    .leads-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-3);
        overflow: hidden;
    }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        gap: 1rem;
        flex-wrap: wrap;
    }

    .panel-title-row {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .panel-title {
        font-family: 'Syne', sans-serif;
        font-size: 1rem;
        font-weight: 700;
        color: var(--t1);
    }

    .badge {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--accent);
        background: rgba(109,93,252,0.15);
        border-radius: 100px;
        padding: 0.15rem 0.6rem;
    }

    .search-box {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: var(--r-2);
        padding: 0.55rem 0.9rem;
        min-width: 240px;
        transition: border-color var(--d-1);
    }

    .search-box:focus-within { border-color: var(--accent); }

    .search-box svg { width: 15px; height: 15px; color: var(--t3); flex-shrink: 0; }

    .search-input {
        background: transparent;
        border: none;
        outline: none;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        width: 100%;
    }
    .search-input::placeholder { color: var(--t3); }

    .table-head {
        display: grid;
        grid-template-columns: 2fr 1.5fr 2fr 48px;
        padding: 0.65rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: rgba(0,0,0,0.15);
    }

    .table-head span {
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--t3);
    }

    .table-body { display: flex; flex-direction: column; }

    .table-row {
        display: grid;
        grid-template-columns: 2fr 1.5fr 2fr 48px;
        align-items: center;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(30,40,64,0.5);
        transition: background var(--d-1);
        animation: fadeRow 0.35s cubic-bezier(0.22,1,0.36,1) both;
    }
    .table-row:last-child { border-bottom: none; }
    .table-row { cursor: pointer; }
    .table-row:hover { background: rgba(109,93,252,0.05); }

    @keyframes fadeRow {
        from { opacity: 0; transform: translateX(-8px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .col-lead {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }

    .lead-avatar {
        width: 36px;
        height: 36px;
        border-radius: var(--r-2);
        background: var(--accent-dim); color: var(--accent-hi);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Syne', sans-serif;
        font-size: 0.7rem;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }

    .lead-name-block { min-width: 0; }

    .lead-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--t1);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lead-email {
        font-size: 0.775rem;
        color: var(--t3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .col-contact { display: flex; align-items: center; }

    .phone-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8rem;
        color: var(--t2);
    }
    .phone-tag svg { width: 13px; height: 13px; color: var(--t3); }

    .col-desc { min-width: 0; }

    .desc-text {
        font-size: 0.8rem;
        color: var(--t3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .col-action { display: flex; justify-content: flex-end; }

    .row-action {
        width: 32px;
        height: 32px;
        border-radius: var(--r-2);
        border: 1px solid var(--border);
        background: transparent;
        color: var(--t3);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: color var(--d-1), border-color 0.15s, background 0.15s;
    }

    .row-action svg { width: 14px; height: 14px; }
    .row-action:hover {
        color: var(--accent);
        border-color: rgba(109,93,252,0.4);
        background: rgba(109,93,252,0.08);
    }

    .state-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
        gap: 0.75rem;
        text-align: center;
    }

    .empty-icon { width: 48px; height: 48px; color: var(--t3); opacity: 0.4; }
    .empty-icon svg { width: 100%; height: 100%; }

    .state-title { font-size: 0.95rem; font-weight: 500; color: var(--t2); }
    .state-sub { font-size: 0.82rem; color: var(--t3); }

    .spinner {
        width: 28px;
        height: 28px;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.65s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(6px);
        padding: 1.5rem;
    }

    .modal {
        width: 100%;
        max-width: 480px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-3);
        padding: 2rem;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.04) inset, 0 40px 80px rgba(0,0,0,0.6), 0 0 60px var(--glow);
    }

    .modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1.75rem;
    }

    .modal-title {
        font-family: 'Syne', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--t1);
        letter-spacing: -0.4px;
        margin-bottom: 0.2rem;
    }
    .modal-sub { font-size: 0.82rem; color: var(--t2); font-weight: 300; }

    .modal-close {
        width: 30px; height: 30px;
        border: 1px solid var(--border);
        background: transparent;
        border-radius: var(--r-1);
        color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: color var(--d-1), border-color 0.2s;
    }
    .modal-close svg { width: 15px; height: 15px; }
    .modal-close:hover { color: var(--t1); border-color: var(--t2); }

    .modal-form { display: flex; flex-direction: column; gap: 1.1rem; }

    .field { display: flex; flex-direction: column; gap: 0.4rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    .field-label {
        font-size: 0.71rem;
        font-weight: 500;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--t3);
    }

    .field-input {
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: var(--r-2);
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        padding: 0.7rem 0.9rem;
        outline: none;
        transition: border-color var(--d-1), box-shadow 0.2s;
        width: 100%;
        box-sizing: border-box;
    }

    .field-input::placeholder { color: var(--t3); }
    .field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--glow); }
    .field-input--error { border-color: var(--danger) !important; }
    .field-input--error:focus { box-shadow: 0 0 0 3px rgba(240,98,146,0.15) !important; }

    .field-error {
        font-size: 0.72rem;
        color: var(--danger);
        margin-top: 0.2rem;
    }
    .field-error--geral {
        text-align: center;
        padding: 0.5rem 0.75rem;
        background: rgba(240,98,146,0.08);
        border: 1px solid rgba(240,98,146,0.25);
        border-radius: var(--r-2);
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .modal-enter-active, .modal-leave-active { transition: opacity 0.22s var(--e); }
    .modal-enter-active .modal, .modal-leave-active .modal { transition: transform 0.22s cubic-bezier(0.22,1,0.36,1), opacity 0.22s var(--e); }
    .modal-enter-from, .modal-leave-to { opacity: 0; }
    .modal-enter-from .modal, .modal-leave-to .modal { transform: scale(0.95) translateY(12px); opacity: 0; }

    /* ── Métricas ── */
    .metrics-section { margin-bottom: 2rem; }

    .summary-sub {
        font-size: 0.68rem;
        color: var(--t3);
        margin-top: 0.1rem;
    }
    .summary-text-overflow { min-width: 0; overflow: hidden; }
    .summary-value--sm {
        font-size: 1.05rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Skeleton */
    .skel-card { min-height: 82px; animation: skel-pulse 1.4s ease-in-out infinite; }
    .skel-block {
        background: rgba(255,255,255,0.04);
        border-radius: var(--r-3);
        animation: skel-pulse 1.4s ease-in-out infinite;
        border: 1px solid var(--border);
    }
    .skel-chart { flex: 1; height: 180px; }
    .skel-donut { width: 240px; height: 180px; flex-shrink: 0; }
    @keyframes skel-pulse {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.45; }
    }

    /* Row 2 */
    .metrics-row2 {
        display: flex;
        gap: 1rem;
        align-items: stretch;
    }

    /* Cards de gráfico */
    .metric-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-3);
        padding: 1.25rem 1.5rem;
        transition: border-color var(--d-1);
    }
    .metric-card:hover { border-color: rgba(109,93,252,0.28); }
    .metric-card-title {
        font-family: 'Syne', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--t2);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }
    .metric-empty { font-size: 0.8rem; color: var(--t3); }

    /* Barras */
    .chart-card { flex: 1; }
    .bar-list { display: flex; flex-direction: column; gap: 0.7rem; }
    .bar-row { display: flex; align-items: center; gap: 0.65rem; }
    .bar-label {
        font-size: 0.75rem;
        color: var(--t2);
        width: 130px;
        flex-shrink: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .bar-track {
        flex: 1;
        height: 8px;
        background: rgba(255,255,255,0.05);
        border-radius: 100px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 100px;
        transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        min-width: 4px;
    }
    .bar-count {
        font-size: 0.75rem;
        font-weight: 600;
        width: 20px;
        text-align: right;
        flex-shrink: 0;
    }

    /* Donut */
    .conv-card {
        width: 220px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .donut-wrap { width: 130px; height: 130px; margin: 0 auto 0.75rem; }
    .donut-svg { width: 100%; height: 100%; overflow: visible; }
    .conv-legend {
        font-size: 0.78rem;
        color: var(--t3);
        text-align: center;
        line-height: 1.5;
    }
    .conv-highlight {
        font-weight: 600;
        color: var(--t2);
    }

    /* ── Widget de Tarefas ── */
    .tasks-widget {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        margin-bottom: 1.25rem;
    }

    .tasks-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.85rem 1.1rem;
        border-radius: var(--r-3);
        border: 1px solid;
        flex-wrap: wrap;
    }
    .tasks-alert svg { flex-shrink: 0; margin-top: 1px; }

    .tasks-alert--danger {
        background: rgba(239,68,68,0.07);
        border-color: rgba(239,68,68,0.25);
        color: var(--danger);
    }
    .tasks-alert--today {
        background: rgba(245,158,11,0.07);
        border-color: rgba(245,158,11,0.25);
        color: var(--warn);
    }

    .tasks-alert-label {
        font-size: 0.82rem;
        font-weight: 600;
        white-space: nowrap;
        padding-top: 1px;
    }

    .tasks-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        flex: 1;
    }

    .task-chip {
        font-size: 0.72rem;
        font-weight: 500;
        padding: 0.2rem 0.65rem;
        border-radius: 100px;
        cursor: pointer;
        transition: opacity var(--d-1), transform 0.15s;
        white-space: nowrap;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .task-chip:hover { opacity: 0.8; transform: translateY(-1px); }

    .task-chip--danger {
        background: rgba(239,68,68,0.12);
        border: 1px solid rgba(239,68,68,0.3);
        color: var(--danger);
    }
    .task-chip--today {
        background: rgba(245,158,11,0.12);
        border: 1px solid rgba(245,158,11,0.3);
        color: var(--warn);
    }

    .task-chip-more {
        font-size: 0.7rem;
        color: var(--t3);
        padding: 0.2rem 0.4rem;
        align-self: center;
    }

    /* ── Barra de Filtros ── */
    .filter-bar {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
        padding: 0.85rem 0 0;
        border-top: 1px solid var(--border);
        margin-top: 0.75rem;
    }

    .fb-row {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        flex-wrap: wrap;
    }

    .fb-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--t3);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        white-space: nowrap;
        min-width: 52px;
    }

    .fb-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }

    .fb-chip {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.74rem; font-weight: 500;
        padding: 0.25rem 0.7rem; border-radius: 100px;
        border: 1px solid var(--border);
        background: transparent; color: var(--t3);
        cursor: pointer;
        transition: all var(--d-1);
    }
    .fb-chip:hover { color: var(--fg-0); border-color: var(--line-2); }
    .fb-chip--active {
        background: rgba(109,93,252,0.12);
        border-color: rgba(109,93,252,0.35);
        color: var(--accent);
    }

    .fb-date-range {
        display: flex; align-items: center; gap: 0.4rem;
    }
    .fb-date-input {
        background: var(--inp-bg); border: 1px solid var(--border); border-radius: var(--r-2);
        color: var(--t1); font-family: 'DM Sans', sans-serif; font-size: 0.78rem;
        padding: 0.28rem 0.55rem; outline: none; color-scheme: dark;
        transition: border-color var(--d-1);
    }
    .fb-date-input:focus { border-color: var(--accent); }
    .fb-date-sep { font-size: 0.72rem; color: var(--t3); }

    .fb-clear-row {
        display: flex; align-items: center; gap: 0.75rem; margin-top: 0.1rem;
    }
    .fb-clear-btn {
        font-family: 'DM Sans', sans-serif; font-size: 0.72rem; font-weight: 500;
        padding: 0.2rem 0.65rem; border-radius: 100px;
        border: 1px solid rgba(239,68,68,0.3); background: rgba(239,68,68,0.08);
        color: var(--danger); cursor: pointer;
        transition: background var(--d-1);
    }
    .fb-clear-btn:hover { background: rgba(239,68,68,0.15); }
    .fb-result-count { font-size: 0.72rem; color: var(--t3); }

    .fb-slide-enter-active, .fb-slide-leave-active { transition: opacity 0.18s, transform 0.18s; }
    .fb-slide-enter-from, .fb-slide-leave-to { opacity: 0; transform: translateY(-4px); }

    @media (max-width: 900px) {
        .content { padding: 1.75rem 1.5rem; }
        .summary-row { grid-template-columns: repeat(2, 1fr); }
        .metrics-row2 { flex-direction: column; }
        .conv-card { width: 100%; }
        .skel-donut { width: 100%; }
        .table-head, .table-row { grid-template-columns: 1fr 1fr 36px; }
        .col-desc { display: none; }
        .table-head span:nth-child(3) { display: none; }
    }

    @media (max-width: 600px) {
        .content { padding: 1.25rem 1rem; }
        .topbar { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .btn-primary { width: 100%; justify-content: center; }
        .topbar-title { font-size: 1.5rem; }
        .summary-row { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .bar-label { width: 90px; }
        .panel-header { flex-direction: column; align-items: stretch; }
        .search-box { min-width: unset; }
        .table-head, .table-row { grid-template-columns: 1fr 36px; }
        .col-contact { display: none; }
        .table-head span:nth-child(2) { display: none; }
        .field-row { grid-template-columns: 1fr; }
        .modal-footer { flex-direction: column-reverse; }
        .btn-ghost, .btn-primary { width: 100%; justify-content: center; }
    }
    /* ══════════════════════════════════════════════════════════
       Densidade e tipografia — a parte que não dá para automatizar.

       O que muda em relação ao layout anterior: os quatro cartões de resumo
       deixam de ser caixas com ícone grande e viram número + rótulo, que é o
       que se lê de fato; a tabela aperta para caber mais lead na tela sem
       rolar; e a escala de tipo passa a sair dos tokens em vez de valores
       soltos por regra.
       ══════════════════════════════════════════════════════════ */

    .topbar { align-items: center; gap: var(--s-4); margin-bottom: var(--s-6); }
    .topbar-greeting { font-size: var(--fs-md); color: var(--fg-2); font-weight: 400; }
    .topbar-title { font-family: var(--font-display); font-size: var(--fs-xl); font-weight: 700; letter-spacing: -.3px; margin-top: 2px; }

    .summary-row { gap: var(--s-3); }
    .summary-card {
        gap: var(--s-3); padding: var(--s-4);
        border-radius: var(--r-3); background: var(--bg-1); border: 1px solid var(--line);
        transition: border-color var(--d-1) var(--e);
    }
    .summary-card:hover { border-color: var(--line-2); }
    /* Ícone recuado: ele identifica o cartão, não é o conteúdo dele. */
    .summary-icon { width: 30px; height: 30px; border-radius: var(--r-2); flex-shrink: 0; }
    .summary-icon svg { width: 15px; height: 15px; }

    .summary-value { font-family: var(--font-display); font-size: 22px; font-weight: 700; line-height: 1.1; letter-spacing: -.4px; }
    .summary-value--sm { font-size: 17px; }
    .summary-label { font-size: var(--fs-md); color: var(--fg-1); margin-top: 3px; }
    .summary-sub { font-size: var(--fs-xs); color: var(--fg-2); margin-top: 1px; }

    /* Cor por significado, vinda dos tokens — antes era hex inline no template. */
    .summary-value.is-ok   { color: var(--ok); }
    .summary-value.is-info { color: var(--info); }
    .summary-value.is-warn { color: var(--warn); }
    .summary-value.is-cat  { color: var(--cat-3); }

    .panel-title { font-family: var(--font-display); font-size: var(--fs-lg); font-weight: 700; }
    .metric-card-title { font-size: var(--fs-md); color: var(--fg-1); }

    /* ── Tabela ──────────────────────────────────────────────── */
    .table-head {
        font-size: var(--fs-xs); letter-spacing: .07em; text-transform: uppercase;
        color: var(--fg-2); padding: var(--s-2) var(--s-4);
    }
    .table-row {
        padding: 9px var(--s-4); gap: var(--s-3);
        border-radius: var(--r-2);
        transition: background var(--d-1) var(--e);
        animation: linha-entra var(--d-2) var(--e) backwards;
        animation-delay: calc(var(--i, 0) * 20ms);
    }
    @keyframes linha-entra { from { opacity: 0; transform: translateY(3px); } }
    .table-row:hover { background: var(--bg-2); }

    .lead-avatar {
        width: 30px; height: 30px; border-radius: var(--r-2);
        font-size: 11px; font-weight: 600; font-family: var(--font-display);
    }
    .lead-name { font-size: var(--fs-md); font-weight: 500; }
    .lead-email { font-size: var(--fs-sm); color: var(--fg-2); }
    .desc-text, .phone-tag { font-size: var(--fs-sm); color: var(--fg-1); }

    /* Ação da linha só aparece quando a linha é o foco — reduz o ruído de
       uma coluna de botões repetidos descendo a tela inteira. */
    .row-action { opacity: 0; transition: opacity var(--d-1) var(--e), color var(--d-1) var(--e); }
    .table-row:hover .row-action, .table-row:focus-within .row-action { opacity: 1; }

    /* Paginação da listagem (ListagemDeLeads, no servidor). */
    .db-paginacao { display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; margin-top:1rem; font-size:0.8rem; color:var(--t2); }

</style>