<script setup>
    import { ref, onMounted, computed } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
    import axios from 'axios';
    import { vMaska } from 'maska/vue';
    import { PlusCircle, FileText, Paperclip, Trash2, Edit, Save, X, Download, Upload, ChevronLeft, CheckCircle2, AlertCircle } from 'lucide-vue-next';
    import Swal from 'sweetalert2';

    const user = computed(() => usePage().props.auth.user);

    const usuario = ref(null);
    const anotacoes = ref([]);
    const arquivos = ref([]);
    const editingNoteId = ref(null);
    const showAddNote = ref(false);
    const isUploading = ref(false);
    const uploadProgress = ref(0);
    const isSavingUser = ref(false);
    const idPerfil = sessionStorage.getItem('idPerfil');
    const arquivosPendentes = ref([]);
    const toast = ref({ show: false, message: '', type: 'success' });

    const noteForm = useForm({ descricao: '', usuario_id: user.value.id });
    const editNoteForm = useForm({ id: '', descricao: '', usuario_id: user.value.id });

    let toastTimer = null;
    const showToast = (message, type = 'success') => {
        clearTimeout(toastTimer);
        toast.value = { show: true, message, type };
        toastTimer = setTimeout(() => { toast.value.show = false; }, 3500);
    };

    const messageError = (msg) => showToast(msg, 'error');

    // ── Usuário ──
    const buscarUsuario = async (id) => {
        try {
            const response = await axios.get(`/api/usuarioPerfil/${id}`);
            usuario.value = response.data[0];
        } catch {
            messageError('Erro ao buscar usuário!');
        }
    };

    const editarUsuario = async () => {
        isSavingUser.value = true;
        usuario.value.telefone = usuario.value.telefone?.replace(/\D/g, '');
        try {
            await axios.put(`api/usuarios/${usuario.value.id}`, { ...usuario.value });
            await buscarUsuario(idPerfil);
            showToast('Dados do lead salvos com sucesso!');
        } catch {
            messageError('Erro ao atualizar usuário!');
        } finally {
            isSavingUser.value = false;
        }
    };

    // ── Anotações ──
    const buscarAnotacao = async (id) => {
        try {
            const response = await axios.get(`/api/anotacao/${id}`);
            anotacoes.value = response.data;
        } catch {
            messageError('Erro ao buscar anotação!');
        }
    };

    const cadastrarAnotacao = async () => {
        if (!noteForm.descricao.trim()) return;
        try {
            await axios.post(`api/anotacao`, { ...noteForm });
            showToast('Anotação criada com sucesso!');
            await buscarAnotacao(idPerfil);
            noteForm.descricao = '';
            showAddNote.value = false;
        } catch {
            messageError('Erro ao criar anotação!');
        }
    };

    const editarAnotacao = (anotacao) => {
        editNoteForm.id = anotacao.id;
        editNoteForm.descricao = anotacao.descricao;
        editingNoteId.value = anotacao.id;
    };

    const cancelarEdicaoAnotacao = () => { editingNoteId.value = null; };

    const salvarEdicaoAnotacao = async () => {
        try {
            await axios.put(`api/anotacao/${editNoteForm.id}`, editNoteForm);
            showToast('Anotação atualizada!');
            buscarAnotacao(idPerfil);
            editingNoteId.value = null;
        } catch {
            messageError('Erro ao editar anotação!');
        }
    };

    const excluirAnotacao = async (id) => {
        const result = await Swal.fire({
            title: 'Apagar anotação?',
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
                await axios.delete(`api/anotacao/${id}`);
                showToast('Anotação removida.');
                buscarAnotacao(idPerfil);
            } catch {
                messageError('Erro ao apagar anotação!');
            }
        }
    };

    // ── Arquivos ──
    const buscarAnexo = async (id) => {
        try {
            const response = await axios.post(`/api/buscarArquivo/`, { user_id: id });
            arquivos.value = response.data;
        } catch {
            messageError('Erro ao buscar anexos!');
        }
    };

    const adicionarArquivos = (event) => {
        const files = Array.from(event.target.files);
        files.forEach(file => {
            const jaExiste = arquivosPendentes.value.some(a => a.name === file.name && a.size === file.size);
            if (!jaExiste) {
                arquivosPendentes.value.push({
                    id: Date.now() + Math.random(),
                    file, name: file.name, size: file.size, type: file.type, isPending: true,
                });
            }
        });
        event.target.value = null;
    };

    const removerArquivoPendente = (id) => {
        arquivosPendentes.value = arquivosPendentes.value.filter(a => a.id !== id);
    };

    const salvarArquivos = async () => {
        if (!arquivosPendentes.value.length) return;
        isUploading.value = true;
        uploadProgress.value = 0;
        try {
            const total = arquivosPendentes.value.length;
            let processados = 0;
            for (const arq of arquivosPendentes.value) {
                const formData = new FormData();
                formData.append('arquivo', arq.file);
                formData.append('nome', arq.name);
                formData.append('usuario_id', idPerfil);
                await axios.post('api/arquivos', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
                processados++;
                uploadProgress.value = Math.round((processados / total) * 100);
            }
            arquivosPendentes.value = [];
            buscarAnexo(idPerfil);
            showToast(`${total} arquivo(s) enviado(s) com sucesso!`);
        } catch {
            messageError('Erro ao enviar arquivos!');
        } finally {
            isUploading.value = false;
        }
    };

    const formatarTamanho = (bytes) => {
        if (!bytes) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    };

    const excluirArquivo = async (id) => {
        const result = await Swal.fire({
            title: 'Apagar documento?',
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
                await axios.delete(`api/arquivos/${id}`);
                showToast('Documento removido.');
                buscarAnexo(idPerfil);
            } catch {
                messageError('Erro ao apagar arquivo!');
            }
        }
    };

    const getInitials = (nome) =>
        nome?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?';

    onMounted(() => {
        if (idPerfil) {
            buscarUsuario(idPerfil);
            buscarAnotacao(idPerfil);
            buscarAnexo(idPerfil);
        } else {
            router.visit(route('dashboard'));
        }
    });
</script>

<template>
    <Head title="Perfil do Lead — UserFlow" />
    <AuthenticatedLayout>
        <div class="page">
            <div class="dot-grid" aria-hidden="true" />
            <div class="glow-blob" aria-hidden="true" />

            <Transition name="toast">
                <div v-if="toast.show" class="toast" :class="`toast--${toast.type}`">
                    <CheckCircle2 v-if="toast.type === 'success'" :size="15" />
                    <AlertCircle v-else :size="15" />
                    {{ toast.message }}
                </div>
            </Transition>

            <div v-if="!usuario" class="loading-state">
                <div class="spinner" />
                <p class="loading-text">Carregando perfil...</p>
            </div>

            <div v-else class="content">

                <div class="breadcrumb">
                    <Link :href="route('dashboard')" class="breadcrumb-link">
                        <ChevronLeft :size="14" />
                        Dashboard
                    </Link>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">{{ usuario.nome }}</span>
                </div>

                <div class="profile-hero">
                    <div class="hero-left">
                        <div class="profile-avatar">{{ getInitials(usuario.nome) }}</div>
                        <div class="profile-info">
                            <h1 class="profile-name">{{ usuario.nome }}</h1>
                            <div class="profile-meta">
                                <span class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    {{ usuario.email }}
                                </span>
                                <span class="meta-item" v-if="usuario.telefone">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    {{ usuario.telefone }}
                                </span>
                                <span class="meta-badge">
                                    {{ arquivos.length }} doc{{ arquivos.length !== 1 ? 's' : '' }}
                                </span>
                                <span class="meta-badge meta-badge--note">
                                    {{ anotacoes.length }} anotaç{{ anotacoes.length !== 1 ? 'ões' : 'ão' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="three-col">

                    <section class="panel">
                        <div class="panel-header">
                            <div class="panel-title-group">
                                <span class="panel-icon panel-icon--accent">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </span>
                                <h2 class="panel-title">Dados do Lead</h2>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="fields-stack">
                                <div class="edit-field">
                                    <label class="edit-label">Nome completo</label>
                                    <input type="text" v-model="usuario.nome" class="edit-input" placeholder="Nome" />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">E-mail</label>
                                    <input type="email" v-model="usuario.email" class="edit-input" placeholder="email@exemplo.com" />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Telefone</label>
                                    <input
                                        v-model="usuario.telefone"
                                        class="edit-input"
                                        v-maska
                                        data-maska="(##) #####-####"
                                        placeholder="(00) 00000-0000"
                                    />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Descrição</label>
                                    <textarea
                                        v-model="usuario.descricao"
                                        class="edit-input edit-textarea"
                                        rows="3"
                                        placeholder="Observações gerais..."
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="panel-footer">
                            <button
                                @click="editarUsuario"
                                class="btn-save-full"
                                :disabled="isSavingUser"
                            >
                                <span v-if="isSavingUser" class="btn-spinner" />
                                <Save v-else :size="14" />
                                {{ isSavingUser ? 'Salvando...' : 'Salvar dados' }}
                            </button>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <div class="panel-title-group">
                                <span class="panel-icon panel-icon--green">
                                    <Paperclip :size="14" />
                                </span>
                                <h2 class="panel-title">Documentos</h2>
                                <span class="count-chip">{{ arquivos.length }}</span>
                            </div>
                            <label class="btn-icon-action" title="Selecionar arquivos">
                                <PlusCircle :size="14" />
                                <input type="file" multiple class="sr-only" @change="adicionarArquivos" />
                            </label>
                        </div>

                        <Transition name="slide">
                            <div v-if="arquivosPendentes.length > 0" class="pending-zone">
                                <div class="pending-zone-header">
                                    <span class="pending-label">
                                        <Upload :size="12" />
                                        {{ arquivosPendentes.length }} pendente(s)
                                    </span>
                                    <div class="pending-zone-actions">
                                        <button @click="arquivosPendentes = []" class="btn-link-danger" title="Limpar">
                                            <X :size="12" /> Limpar
                                        </button>
                                        <button
                                            @click="salvarArquivos"
                                            class="btn-green"
                                            :disabled="isUploading"
                                        >
                                            <span v-if="isUploading" class="btn-spinner btn-spinner--dark" />
                                            <Save v-else :size="13" />
                                            {{ isUploading ? `${uploadProgress}%` : 'Enviar' }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="isUploading" class="progress-bar-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" :style="{ width: uploadProgress + '%' }" />
                                    </div>
                                </div>

                                <div class="pending-list">
                                    <div v-for="arq in arquivosPendentes" :key="arq.id" class="pending-item">
                                        <div class="pending-file-info">
                                            <div class="file-type-dot" />
                                            <div>
                                                <p class="pending-filename">{{ arq.name }}</p>
                                                <p class="pending-filesize">{{ formatarTamanho(arq.size) }}</p>
                                            </div>
                                        </div>
                                        <button @click="removerArquivoPendente(arq.id)" class="icon-btn icon-btn--delete" title="Remover">
                                            <X :size="12" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </Transition>

                        <div class="panel-body panel-body--flush">
                            <div v-if="arquivos.length > 0" class="files-list">
                                <div
                                    v-for="arquivo in arquivos"
                                    :key="arquivo.id"
                                    class="file-item"
                                >
                                    <div class="file-item-left">
                                        <div class="file-icon-wrap">
                                            <FileText :size="13" />
                                        </div>
                                        <div class="file-item-info">
                                            <span class="file-name">{{ arquivo.nome }}</span>
                                            <span class="file-size">{{ formatarTamanho(arquivo.tamanho || 0) }}</span>
                                        </div>
                                    </div>
                                    <div class="file-item-actions">
                                        <a :href="`/storage/${arquivo.local}`" download class="icon-btn icon-btn--download" title="Download">
                                            <Download :size="13" />
                                        </a>
                                        <button @click="excluirArquivo(arquivo.id)" class="icon-btn icon-btn--delete" title="Excluir">
                                            <Trash2 :size="13" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="!arquivosPendentes.length" class="empty-panel">
                                <div class="empty-panel-icon"><Paperclip :size="22" /></div>
                                <p class="empty-panel-title">Nenhum documento</p>
                                <p class="empty-panel-sub">Clique em + para adicionar arquivos.</p>
                            </div>
                        </div>

                        <div class="panel-footer" v-if="!arquivos.length && !arquivosPendentes.length">
                            <label class="btn-save-full btn-save-full--ghost" style="cursor:pointer; justify-content:center;">
                                <Paperclip :size="14" />
                                Selecionar documentos
                                <input type="file" multiple class="sr-only" @change="adicionarArquivos" />
                            </label>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <div class="panel-title-group">
                                <span class="panel-icon panel-icon--purple">
                                    <FileText :size="14" />
                                </span>
                                <h2 class="panel-title">Anotações</h2>
                                <span class="count-chip count-chip--purple">{{ anotacoes.length }}</span>
                            </div>
                            <button
                                v-if="!showAddNote"
                                @click="showAddNote = true"
                                class="btn-icon-action btn-icon-action--purple"
                                title="Nova anotação"
                            >
                                <PlusCircle :size="14" />
                            </button>
                            <button
                                v-else
                                @click="showAddNote = false"
                                class="btn-icon-action btn-icon-action--ghost"
                                title="Cancelar"
                            >
                                <X :size="14" />
                            </button>
                        </div>

                        <Transition name="slide">
                            <div v-if="showAddNote" class="note-form-inline">
                                <textarea
                                    v-model="noteForm.descricao"
                                    rows="3"
                                    placeholder="Digite sua anotação..."
                                    class="note-textarea"
                                    autofocus
                                />
                                <div class="note-form-actions">
                                    <button @click="showAddNote = false; noteForm.descricao = ''" class="btn-ghost btn-sm">
                                        <X :size="12" /> Cancelar
                                    </button>
                                    <button
                                        @click="cadastrarAnotacao"
                                        class="btn-primary btn-sm"
                                        :disabled="!noteForm.descricao.trim()"
                                    >
                                        <Save :size="12" /> Salvar
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <div class="panel-body panel-body--flush panel-body--scroll">
                            <div v-if="anotacoes.length > 0" class="notes-list">
                                <div
                                    v-for="(anotacao, i) in anotacoes"
                                    :key="anotacao.id"
                                    class="note-item"
                                    :style="{ animationDelay: `${i * 0.04}s` }"
                                >
                                    <div v-if="editingNoteId !== anotacao.id" class="note-view">
                                        <p class="note-text">{{ anotacao.descricao }}</p>
                                        <div class="note-actions">
                                            <button @click="editarAnotacao(anotacao)" class="icon-btn icon-btn--edit" title="Editar">
                                                <Edit :size="12" />
                                            </button>
                                            <button @click="excluirAnotacao(anotacao.id)" class="icon-btn icon-btn--delete" title="Excluir">
                                                <Trash2 :size="12" />
                                            </button>
                                        </div>
                                    </div>

                                    <div v-else class="note-edit">
                                        <textarea
                                            v-model="editNoteForm.descricao"
                                            rows="3"
                                            class="note-textarea"
                                        />
                                        <div class="note-form-actions">
                                            <button @click="cancelarEdicaoAnotacao" class="btn-ghost btn-sm">
                                                <X :size="12" /> Cancelar
                                            </button>
                                            <button @click="salvarEdicaoAnotacao" class="btn-primary btn-sm">
                                                <Save :size="12" /> Salvar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else-if="!showAddNote" class="empty-panel">
                                <div class="empty-panel-icon"><FileText :size="22" /></div>
                                <p class="empty-panel-title">Nenhuma anotação</p>
                                <p class="empty-panel-sub">Clique em + para registrar uma observação.</p>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
    body, #app { background: #0d1117 !important; margin: 0; padding: 0; }
</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap');

    .page {
        --bg:       #0d1117;
        --surface:  #13192a;
        --surface2: #0f1420;
        --border:   #1e2840;
        --border-h: #2a3758;
        --accent:   #6d5dfc;
        --accent-h: #7c6efd;
        --glow:     rgba(109, 93, 252, 0.22);
        --green:    #3ecf8e;
        --green-bg: rgba(62, 207, 142, 0.1);
        --red:      #f06292;
        --red-bg:   rgba(240, 98, 146, 0.1);
        --purple:   #a78bfa;
        --purple-bg: rgba(167, 139, 250, 0.1);
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

    /* ── Background ── */
    .dot-grid {
        position: fixed; inset: 0;
        background-image: radial-gradient(circle, #1c2540 1px, transparent 1px);
        background-size: 30px 30px;
        opacity: 0.45; pointer-events: none; z-index: 0;
    }
    .glow-blob {
        position: fixed; top: -200px; right: -150px;
        width: 600px; height: 600px;
        background: radial-gradient(circle, rgba(109,93,252,0.14) 0%, transparent 70%);
        pointer-events: none; z-index: 0;
    }

    /* ── Toast ── */
    .toast {
        position: fixed; top: 1.25rem; right: 1.5rem; z-index: 9999;
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.65rem 1.1rem;
        border-radius: 10px;
        font-size: 0.82rem; font-weight: 500;
        backdrop-filter: blur(12px);
        border: 1px solid transparent;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    }
    .toast--success {
        background: rgba(62,207,142,0.15);
        border-color: rgba(62,207,142,0.3);
        color: var(--green);
    }
    .toast--error {
        background: rgba(240,98,146,0.15);
        border-color: rgba(240,98,146,0.3);
        color: var(--red);
    }
    .toast-enter-active, .toast-leave-active { transition: opacity 0.25s, transform 0.25s; }
    .toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(16px); }

    /* ── Loading ── */
    .loading-state {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        min-height: 60vh; gap: 1rem;
    }
    .spinner {
        width: 28px; height: 28px;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.65s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text { font-size: 0.85rem; color: var(--t3); }

    /* ── Content ── */
    .content {
        position: relative; z-index: 1;
        padding: 2.5rem 2.5rem 3rem;
        max-width: 1400px;
    }

    /* ── Breadcrumb ── */
    .breadcrumb {
        display: flex; align-items: center; gap: 0.5rem;
        margin-bottom: 1.75rem;
    }
    .breadcrumb-link {
        display: inline-flex; align-items: center; gap: 0.3rem;
        font-size: 0.8rem; color: var(--t3); text-decoration: none;
        transition: color 0.2s;
    }
    .breadcrumb-link:hover { color: var(--accent); }
    .breadcrumb-sep { color: var(--t3); font-size: 0.8rem; }
    .breadcrumb-current { font-size: 0.8rem; color: var(--t2); }

    /* ── Hero ── */
    .profile-hero {
        display: flex; align-items: center;
        justify-content: space-between;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
        gap: 1rem; flex-wrap: wrap;
    }
    .hero-left { display: flex; align-items: center; gap: 1.1rem; }
    .profile-avatar {
        width: 50px; height: 50px;
        border-radius: 13px;
        background: linear-gradient(135deg, var(--accent), #9c52f2);
        display: flex; align-items: center; justify-content: center;
        font-family: 'Syne', sans-serif;
        font-size: 1rem; font-weight: 700; color: #fff;
        flex-shrink: 0;
    }
    .profile-name {
        font-family: 'Syne', sans-serif;
        font-size: 1.25rem; font-weight: 800;
        color: var(--t1); margin: 0 0 0.3rem;
        letter-spacing: -0.3px;
    }
    .profile-meta { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
    .meta-item {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-size: 0.8rem; color: var(--t2);
    }
    .meta-item svg { width: 12px; height: 12px; }
    .meta-badge {
        font-size: 0.7rem; font-weight: 500;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border);
        color: var(--t3); border-radius: 100px;
        padding: 0.15rem 0.6rem;
    }
    .meta-badge--note {
        background: var(--purple-bg);
        border-color: rgba(167,139,250,0.2);
        color: var(--purple);
    }

    /* ── 3-col layout ── */
    .three-col {
        display: grid;
        grid-template-columns: 340px 1fr 1fr;
        gap: 1.25rem;
        align-items: start;
    }

    /* ── Panel ── */
    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        display: flex; flex-direction: column;
    }

    .panel-header {
        display: flex; align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        gap: 0.5rem;
    }
    .panel-title-group {
        display: flex; align-items: center; gap: 0.55rem;
    }
    .panel-title {
        font-family: 'Syne', sans-serif;
        font-size: 0.875rem; font-weight: 700;
        color: var(--t1); margin: 0;
    }

    .panel-icon {
        width: 26px; height: 26px;
        border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .panel-icon--accent { background: rgba(109,93,252,0.15); color: var(--accent); }
    .panel-icon--green  { background: var(--green-bg); color: var(--green); }
    .panel-icon--purple { background: var(--purple-bg); color: var(--purple); }

    .count-chip {
        font-size: 0.68rem; font-weight: 600;
        background: rgba(255,255,255,0.06);
        border: 1px solid var(--border);
        color: var(--t3); border-radius: 100px;
        padding: 0.1rem 0.5rem;
    }
    .count-chip--purple {
        background: var(--purple-bg);
        border-color: rgba(167,139,250,0.2);
        color: var(--purple);
    }

    .panel-body {
        padding: 1.25rem;
        flex: 1;
    }
    .panel-body--flush { padding: 0; }
    .panel-body--scroll { max-height: 480px; overflow-y: auto; }
    .panel-body--scroll::-webkit-scrollbar { width: 4px; }
    .panel-body--scroll::-webkit-scrollbar-track { background: transparent; }
    .panel-body--scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .panel-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
    }

    /* ── Fields ── */
    .fields-stack { display: flex; flex-direction: column; gap: 0.9rem; padding: 1.25rem; }
    .edit-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .edit-label {
        font-size: 0.7rem; font-weight: 500;
        letter-spacing: 0.07em; text-transform: uppercase;
        color: var(--t3);
    }
    .edit-input {
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: 9px;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        padding: 0.6rem 0.85rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%; box-sizing: border-box;
    }
    .edit-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--glow);
    }
    .edit-input::placeholder { color: var(--t3); }
    .edit-textarea { resize: vertical; min-height: 72px; }

    /* ── Save button ── */
    .btn-save-full {
        display: flex; align-items: center; justify-content: center; gap: 0.45rem;
        width: 100%;
        background: var(--accent); color: #fff;
        border: none; border-radius: 9px;
        padding: 0.7rem 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem; font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, box-shadow 0.2s, opacity 0.2s;
    }
    .btn-save-full:hover:not(:disabled) {
        background: var(--accent-h);
        box-shadow: 0 0 20px var(--glow);
    }
    .btn-save-full:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-save-full--ghost {
        background: transparent;
        border: 1px dashed var(--border);
        color: var(--t3);
    }
    .btn-save-full--ghost:hover { border-color: var(--green); color: var(--green); background: var(--green-bg); }

    /* ── Icon action button (header) ── */
    .btn-icon-action {
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 8px;
        background: var(--green-bg);
        border: 1px solid rgba(62,207,142,0.25);
        color: var(--green);
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
        text-decoration: none;
    }
    .btn-icon-action:hover { background: rgba(62,207,142,0.2); border-color: rgba(62,207,142,0.4); }
    .btn-icon-action--purple {
        background: var(--purple-bg);
        border-color: rgba(167,139,250,0.25);
        color: var(--purple);
    }
    .btn-icon-action--purple:hover { background: rgba(167,139,250,0.2); border-color: rgba(167,139,250,0.45); }
    .btn-icon-action--ghost {
        background: transparent;
        border-color: var(--border);
        color: var(--t3);
    }
    .btn-icon-action--ghost:hover { color: var(--t2); border-color: var(--t3); }

    /* ── Pending zone ── */
    .pending-zone {
        background: rgba(109,93,252,0.05);
        border-bottom: 1px solid rgba(109,93,252,0.15);
        padding: 0.85rem 1.1rem;
        display: flex; flex-direction: column; gap: 0.65rem;
    }
    .pending-zone-header {
        display: flex; align-items: center;
        justify-content: space-between; gap: 0.5rem;
    }
    .pending-label {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-size: 0.75rem; font-weight: 500; color: var(--accent);
    }
    .pending-zone-actions { display: flex; gap: 0.4rem; align-items: center; }
    .btn-link-danger {
        display: inline-flex; align-items: center; gap: 0.3rem;
        font-size: 0.75rem; color: var(--t3);
        background: transparent; border: none;
        cursor: pointer; padding: 0.3rem 0.5rem;
        border-radius: 6px;
        transition: color 0.2s;
    }
    .btn-link-danger:hover { color: var(--red); }

    .progress-bar-wrap { }
    .progress-bar {
        height: 3px; background: var(--border);
        border-radius: 100px; overflow: hidden;
    }
    .progress-fill {
        height: 100%; background: var(--accent);
        border-radius: 100px;
        transition: width 0.3s ease;
    }

    .pending-list { display: flex; flex-direction: column; gap: 0.4rem; }
    .pending-item {
        display: flex; align-items: center;
        justify-content: space-between;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
    }
    .pending-file-info { display: flex; align-items: center; gap: 0.55rem; }
    .file-type-dot {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: var(--accent);
        flex-shrink: 0;
    }
    .pending-filename { font-size: 0.78rem; font-weight: 500; color: var(--t1); }
    .pending-filesize { font-size: 0.68rem; color: var(--t3); }

    /* ── Files list ── */
    .files-list { display: flex; flex-direction: column; }
    .file-item {
        display: flex; align-items: center;
        justify-content: space-between;
        padding: 0.8rem 1.25rem;
        border-bottom: 1px solid rgba(30,40,64,0.6);
        transition: background 0.15s;
    }
    .file-item:last-child { border-bottom: none; }
    .file-item:hover { background: rgba(109,93,252,0.04); }
    .file-item-left { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
    .file-icon-wrap {
        width: 28px; height: 28px;
        border-radius: 7px;
        background: var(--green-bg);
        color: var(--green);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .file-item-info { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; }
    .file-name {
        font-size: 0.82rem; color: var(--t1);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: 160px;
    }
    .file-size { font-size: 0.7rem; color: var(--t3); }
    .file-item-actions { display: flex; gap: 0.35rem; }

    /* ── Notes ── */
    .note-form-inline {
        padding: 0.85rem 1.1rem;
        border-bottom: 1px solid var(--border);
        background: rgba(167,139,250,0.04);
        display: flex; flex-direction: column; gap: 0.6rem;
    }
    .note-textarea {
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: 9px;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        padding: 0.65rem 0.9rem;
        outline: none; resize: vertical; width: 100%;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .note-textarea:focus {
        border-color: var(--purple);
        box-shadow: 0 0 0 3px rgba(167,139,250,0.15);
    }
    .note-textarea::placeholder { color: var(--t3); }
    .note-form-actions { display: flex; justify-content: flex-end; gap: 0.5rem; }

    .notes-list { display: flex; flex-direction: column; }
    .note-item {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid rgba(30,40,64,0.5);
        animation: fadeRow 0.3s cubic-bezier(0.22,1,0.36,1) both;
        transition: background 0.15s;
    }
    .note-item:last-child { border-bottom: none; }
    .note-item:hover { background: rgba(167,139,250,0.03); }

    @keyframes fadeRow {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .note-view {
        display: flex; align-items: flex-start;
        justify-content: space-between; gap: 0.75rem;
    }
    .note-text {
        font-size: 0.85rem; color: var(--t2);
        line-height: 1.6; flex: 1;
        word-break: break-word;
    }
    .note-actions { display: flex; gap: 0.35rem; flex-shrink: 0; }
    .note-edit { display: flex; flex-direction: column; gap: 0.6rem; }

    /* ── Buttons ── */
    .btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--accent); color: #fff;
        border: none; border-radius: 8px;
        padding: 0.5rem 0.9rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, box-shadow 0.2s;
    }
    .btn-primary:hover:not(:disabled) { background: var(--accent-h); box-shadow: 0 0 14px var(--glow); }
    .btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }
    .btn-primary.btn-sm { padding: 0.4rem 0.75rem; font-size: 0.78rem; }

    .btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 0.9rem;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 8px; color: var(--t2);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
    }
    .btn-ghost:hover { border-color: var(--border-h); color: var(--t1); }
    .btn-ghost.btn-sm { padding: 0.4rem 0.75rem; font-size: 0.78rem; }

    .btn-green {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--green-bg);
        border: 1px solid rgba(62,207,142,0.3);
        color: var(--green); border-radius: 8px;
        padding: 0.45rem 0.85rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.78rem; font-weight: 500;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-green:hover:not(:disabled) { background: rgba(62,207,142,0.2); }
    .btn-green:disabled { opacity: 0.5; cursor: not-allowed; }

    .icon-btn {
        width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid var(--border);
        background: transparent; cursor: pointer;
        color: var(--t3); transition: color 0.15s, border-color 0.15s, background 0.15s;
        text-decoration: none; flex-shrink: 0;
    }
    .icon-btn--edit:hover   { color: var(--accent); border-color: rgba(109,93,252,0.4); background: rgba(109,93,252,0.08); }
    .icon-btn--delete:hover { color: var(--red); border-color: rgba(240,98,146,0.4); background: var(--red-bg); }
    .icon-btn--download:hover { color: var(--green); border-color: rgba(62,207,142,0.4); background: var(--green-bg); }

    /* ── Spinner inside button ── */
    .btn-spinner {
        width: 12px; height: 12px;
        border: 1.5px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        display: inline-block;
    }
    .btn-spinner--dark {
        border-color: rgba(62,207,142,0.3);
        border-top-color: var(--green);
    }

    /* ── Empty state ── */
    .empty-panel {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 2.5rem 1.5rem; gap: 0.4rem;
        text-align: center;
    }
    .empty-panel-icon { color: var(--t3); opacity: 0.35; margin-bottom: 0.25rem; }
    .empty-panel-title { font-size: 0.875rem; font-weight: 500; color: var(--t2); }
    .empty-panel-sub { font-size: 0.78rem; color: var(--t3); }

    /* ── Transitions ── */
    .slide-enter-active, .slide-leave-active { transition: opacity 0.2s, transform 0.2s; }
    .slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-6px); }

    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); }

    /* ── Responsive ── */
    @media (max-width: 1100px) {
        .three-col { grid-template-columns: 1fr 1fr; }
        .three-col > .panel:first-child { grid-column: 1 / -1; }
    }
    @media (max-width: 700px) {
        .content { padding: 1.25rem 1rem 2rem; }
        .three-col { grid-template-columns: 1fr; }
        .three-col > .panel:first-child { grid-column: auto; }
        .profile-hero { padding: 1.1rem 1.25rem; }
    }
</style>