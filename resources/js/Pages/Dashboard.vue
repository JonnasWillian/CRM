<script setup>
    import { ref, onMounted, computed } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head, router, usePage } from '@inertiajs/vue3';
    import axios from 'axios';
    import { vMaska } from 'maska/vue';

    const usuarios = ref([]);
    const isModalOpen = ref(false);
    const isLoading = ref(false);
    const search = ref('');
    const user = computed(() => usePage().props.auth.user);

    const form = ref({
        nome: '',
        email: '',
        telefone: '',
        descricao: '',
    });

    const filteredUsuarios = computed(() => {
        if (!search.value || !search.value.trim()) {
            return usuarios.value;
        }

        const q = search.value.toLowerCase().trim();

        return usuarios.value.filter(u => {
            const telefoneStr = String(u.telefone || '').trim();

            return (
                u.nome?.toLowerCase().includes(q) ||
                u.email?.toLowerCase().includes(q) ||
                telefoneStr.includes(q) ||
                u.descricao?.toLowerCase().includes(q)
            );
        });
    });

    const buscarUsuarios = async () => {
        isLoading.value = true;
        try {
            const resposta = await axios.post('/api/pegarUsuarios', { 
                user_id: user.value.id 
            });

            usuarios.value = resposta.data.map(usuario => ({
                ...usuario,
                telefone: String(usuario.telefone || '')
            }));
            
        } catch (error) {
            console.error('Erro ao buscar leads:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const addUsuario = async () => {
        const payload = { 
            ...form.value, 
            user_id: user.value.id 
        };

        payload.telefone = payload.telefone.replace(/\D/g, '');

        try {
            await axios.post('/api/usuarios', payload);
            
            await buscarUsuarios();
            closeModal();
            search.value = '';
        } catch (error) {
            console.error('Erro ao adicionar lead:', error);
        }
    };

    const openModal = () => { 
        isModalOpen.value = true; 
    };

    const closeModal = () => {
        form.value = { nome: '', email: '', telefone: '', descricao: '' };
        isModalOpen.value = false;
    };

    const usuarioPerfil = (id) => {
        sessionStorage.setItem('idPerfil', id);
        router.visit(route('perfilUsuario'));
    };

    const getInitials = (nome) =>
        nome?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?';

    onMounted(buscarUsuarios);
</script>

<template>
    <Head title="Dashboard — UserFlow" />

    <AuthenticatedLayout>
        <div class="page">
            <div class="dot-grid" aria-hidden="true" />
            <div class="glow-blob" aria-hidden="true" />

            <div class="p-10">

                <div class="topbar">
                    <div>
                        <p class="topbar-greeting font-bold">Olá, {{ user.name.split(' ')[0] }} 👋</p>
                        <h1 class="topbar-title">Lista de <span class="accent">Leads</span></h1>
                    </div>
                    <button @click="openModal" class="btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Novo Lead
                    </button>
                </div>

                <div class="summary-row">
                    <div class="summary-card summary-card--accent">
                        <div class="summary-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="summary-value">{{ usuarios.length }}</p>
                            <p class="summary-label">Total de Leads</p>
                        </div>
                    </div>
                    <!-- TO DO: analisar indicadores para adicionar -->
                    <!-- <div class="summary-card">
                        <div class="summary-icon summary-icon--green">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="summary-value">99.9%</p>
                            <p class="summary-label">Uptime Garantido</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon summary-icon--blue">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="summary-value">24/7</p>
                            <p class="summary-label">Suporte Dedicado</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon summary-icon--orange">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div>
                            <p class="summary-value">+10k</p>
                            <p class="summary-label">Usuários Gerenciados</p>
                        </div>
                    </div> -->
                </div>

                <div class="leads-panel">
                    <div class="panel-header">
                        <div class="panel-title-row">
                            <h2 class="panel-title">Leads cadastrados</h2>
                            <span class="badge">{{ filteredUsuarios.length }}</span>
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

                    <div v-else-if="filteredUsuarios.length === 0" class="state-center">
                        <div class="empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <p class="state-title">{{ search ? 'Nenhum resultado encontrado' : 'Nenhum lead cadastrado' }}</p>
                        <p class="state-sub">{{ search ? 'Tente buscar por outro termo' : 'Clique em "Novo Lead" para começar' }}</p>
                        <button v-if="!search" @click="openModal" class="btn-primary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            Adicionar primeiro lead
                        </button>
                    </div>

                    <div v-else class="table-body">
                        <div
                            v-for="(usuario, i) in filteredUsuarios"
                            :key="usuario.id"
                            class="table-row"
                            :style="{ animationDelay: `${i * 0.04}s` }"
                        >
                            <div class="col-lead">
                                <div class="lead-avatar">{{ getInitials(usuario.nome) }}</div>
                                <div class="lead-name-block">
                                    <p class="lead-name">{{ usuario.nome }}</p>
                                    <p class="lead-email">{{ usuario.email }}</p>
                                </div>
                            </div>

                            <div class="col-contact">
                                <span class="phone-tag">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    {{ usuario.telefone || '—' }}
                                </span>
                            </div>

                            <div class="col-desc">
                                <p class="desc-text">{{ usuario.descricao || '—' }}</p>
                            </div>

                            <div class="col-action">
                                <button class="row-action" @click="usuarioPerfil(usuario.id)" title="Ver perfil">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
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
                                <input type="text" v-model="form.nome" placeholder="Ex: João Silva" class="field-input" minlength="6" required />
                            </div>
                            <div class="field">
                                <label class="field-label">E-mail*</label>
                                <input type="email" v-model="form.email" placeholder="email@exemplo.com" class="field-input" required />
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Telefone*</label>
                                    <input 
                                        type="tel" 
                                        v-model="form.telefone" 
                                        placeholder="(00) 00000-0000" 
                                        v-maska 
                                        data-maska="(##) #####-####" 
                                        class="field-input" 
                                        required 
                                    />
                                </div>
                                <div class="field">
                                    <label class="field-label">Descrição*</label>
                                    <input type="text" v-model="form.descricao" placeholder="Observação rápida" class="field-input" minlength="6" required />
                                </div>
                            </div>

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
        --bg:       #0d1117;
        --surface:  #13192a;
        --surface2: #0f1420;
        --border:   #1e2840;
        --accent:   #6d5dfc;
        --accent-h: #7c6efd;
        --glow:     rgba(109, 93, 252, 0.22);
        --green:    #3ecf8e;
        --blue:     #38bdf8;
        --orange:   #fb923c;
        --t1:       #eaedf5;
        --t2:       #8892ab;
        --t3:       #4a5470;
        --inp-bg:   #0b0f1a;
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }

    .dot-grid {
        position: fixed;
        inset: 0;
        background-image: radial-gradient(circle, #1c2540 1px, transparent 1px);
        background-size: 30px 30px;
        opacity: 0.45;
        pointer-events: none;
        z-index: 0;
    }

    .glow-blob {
        position: fixed;
        top: -200px;
        right: -150px;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(109,93,252,0.14) 0%, transparent 70%);
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
        border-radius: 10px;
        padding: 0.65rem 1.2rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
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
        border-radius: 10px;
        color: var(--t2);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
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
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: border-color 0.2s, transform 0.15s;
    }

    .summary-card:hover { border-color: rgba(109,93,252,0.3); transform: translateY(-2px); }

    .summary-card--accent { border-color: rgba(109,93,252,0.3); }

    .summary-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
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
        border-radius: 16px;
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
        border-radius: 9px;
        padding: 0.55rem 0.9rem;
        min-width: 240px;
        transition: border-color 0.2s;
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
        transition: background 0.15s;
        animation: fadeRow 0.35s cubic-bezier(0.22,1,0.36,1) both;
    }
    .table-row:last-child { border-bottom: none; }
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
        border-radius: 9px;
        background: linear-gradient(135deg, var(--accent), #9c52f2);
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
        border-radius: 8px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--t3);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: color 0.15s, border-color 0.15s, background 0.15s;
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
        border-radius: 20px;
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
        border-radius: 7px;
        color: var(--t3);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: color 0.2s, border-color 0.2s;
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
        border-radius: 9px;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        padding: 0.7rem 0.9rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
        box-sizing: border-box;
    }

    .field-input::placeholder { color: var(--t3); }
    .field-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--glow); }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .modal-enter-active, .modal-leave-active { transition: opacity 0.22s ease; }
    .modal-enter-active .modal, .modal-leave-active .modal { transition: transform 0.22s cubic-bezier(0.22,1,0.36,1), opacity 0.22s ease; }
    .modal-enter-from, .modal-leave-to { opacity: 0; }
    .modal-enter-from .modal, .modal-leave-to .modal { transform: scale(0.95) translateY(12px); opacity: 0; }

    @media (max-width: 900px) {
        .content { padding: 1.75rem 1.5rem; }
        .summary-row { grid-template-columns: repeat(2, 1fr); }
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
        .panel-header { flex-direction: column; align-items: stretch; }
        .search-box { min-width: unset; }
        .table-head, .table-row { grid-template-columns: 1fr 36px; }
        .col-contact { display: none; }
        .table-head span:nth-child(2) { display: none; }
        .field-row { grid-template-columns: 1fr; }
        .modal-footer { flex-direction: column-reverse; }
        .btn-ghost, .btn-primary { width: 100%; justify-content: center; }
    }
</style>