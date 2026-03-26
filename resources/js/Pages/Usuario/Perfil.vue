<script setup>
    import { ref, onMounted, computed } from 'vue';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
    import axios from 'axios';
    import { vMaska } from 'maska/vue';
    import { PlusCircle, FileText, Paperclip, Trash2, Edit, Save, X, Download, Upload } from 'lucide-vue-next';
    import Swal from 'sweetalert2';

    const user = computed(() => usePage().props.auth.user);

    const usuario = ref(null);
    const anotacoes = ref([]);
    const arquivos = ref([]);
    const activeTab = ref('info');
    const editingNoteId = ref(null);
    const showAddNote = ref(false);
    const isUploading = ref(false);
    const uploadProgress = ref(0);
    const idPerfil = sessionStorage.getItem('idPerfil');
    const arquivosPendentes = ref([]);

    const noteForm = useForm({
        descricao: '',
        usuario_id: user.value.id,
    });

    const editNoteForm = useForm({
        id: '',
        descricao: '',
        usuario_id: user.value.id,
    });

    const messageError = async (messagem) => {
        await Swal.fire({ title: 'Erro!', text: messagem, icon: 'error', confirmButtonText: 'Ok' });
    };

    // ── Usuário ──
    const buscarUsuario = async (id) => {
        try {
            const response = await axios.get(`/api/usuarioPerfil/${id}`);
            usuario.value = response.data[0];
        } catch (error) {
            messageError('Erro ao buscar usuário!');
        }
    };

    const editarUsuario = async () => {
        usuario.value.telefone = usuario.value.telefone.replace(/\D/g, '');
        try {
            await axios.put(`api/usuarios/${usuario.value.id}`, { ...usuario.value });
            await buscarUsuario(idPerfil);
            await Swal.fire({ title: 'Sucesso!', text: 'Usuário atualizado com sucesso!', icon: 'success', confirmButtonText: 'Ok' });
        } catch (error) {
            messageError('Erro ao atualizar usuário!');
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
        try {
            await axios.post(`api/anotacao`, { ...noteForm });
            await Swal.fire({ title: 'Sucesso!', text: 'Anotação criada com sucesso!', icon: 'success', confirmButtonText: 'Ok' });
            await buscarAnotacao(idPerfil); noteForm.descricao = ""; showAddNote.value = false;
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
            buscarAnotacao(idPerfil);
            editingNoteId.value = null;
        } catch {
            messageError('Erro ao editar anotação!');
        }
    };

    const excluirAnotacao = async (id) => {
        const result = await Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente apagar esta anotação?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        });
        if (result.isConfirmed) {
            try {
                await axios.delete(`api/anotacao/${id}`);
                buscarAnotacao(idPerfil);
                await Swal.fire({ title: 'Sucesso!', text: 'Anotação deletada!', icon: 'success', confirmButtonText: 'Ok' });
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
            await Swal.fire({ title: 'Sucesso!', text: `${total} arquivo(s) enviado(s) com sucesso!`, icon: 'success', confirmButtonText: 'OK' });
        } catch {
            messageError('Erro ao enviar arquivos!');
        } finally {
            isUploading.value = false;
        }
    };

    const formatarTamanho = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    };

    const excluirArquivo = async (id) => {
        const result = await Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente apagar este arquivo?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Excluir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        });
        if (result.isConfirmed) {
            try {
                await axios.delete(`api/arquivos/${id}`);
                buscarAnexo(idPerfil);
                await Swal.fire({ title: 'Sucesso!', text: 'Arquivo deletado!', icon: 'success', confirmButtonText: 'Ok' });
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

            <div v-if="!usuario" class="loading-state">
                <div class="spinner" />
                <p class="loading-text">Carregando perfil...</p>
            </div>

            <div v-else class="content">

                <div class="breadcrumb">
                    <Link :href="route('dashboard')" class="breadcrumb-link">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    {{ usuario.email }}
                                </span>
                                <span class="meta-item" v-if="usuario.telefone">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                    {{ usuario.telefone }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button @click="editarUsuario" class="btn-save">
                        <Save :size="15" />
                        Salvar alterações
                    </button>
                </div>

                <div class="tabs-bar">
                    <button
                        v-for="tab in [
                            { id: 'info', label: 'Informações', icon: 'info' },
                            { id: 'anotacoes', label: 'Anotações', badge: anotacoes.length },
                            { id: 'arquivos', label: 'Arquivos', badge: arquivos.length },
                        ]"
                        :key="tab.id"
                        class="tab-btn"
                        :class="{ active: activeTab === tab.id }"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                        <span v-if="tab.badge !== undefined" class="tab-badge" :class="{ 'tab-badge--active': activeTab === tab.id }">
                            {{ tab.badge }}
                        </span>
                    </button>
                </div>

                <div class="tab-content">

                    <div v-if="activeTab === 'info'" class="info-grid">
                        <div class="info-card">
                            <h3 class="card-section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Informações de Contato
                            </h3>
                            <div class="fields-stack">
                                <div class="edit-field">
                                    <label class="edit-label">Nome</label>
                                    <input type="text" v-model="usuario.nome" class="edit-input" />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">E-mail</label>
                                    <input type="email" v-model="usuario.email" class="edit-input" />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Telefone</label>
                                    <input
                                        v-model="usuario.telefone"
                                        class="edit-input"
                                        v-maska
                                        data-maska="(##) #####-####"
                                    />
                                </div>
                                <div class="edit-field">
                                    <label class="edit-label">Descrição</label>
                                    <input type="text" v-model="usuario.descricao" class="edit-input" />
                                </div>
                            </div>
                        </div>

                        <div class="info-card">
                            <h3 class="card-section-title">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Documentos
                            </h3>
                            <div class="empty-docs">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h4a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                <p>Nenhum documento vinculado</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="activeTab === 'anotacoes'" class="tab-section">
                        <div class="section-header">
                            <div>
                                <h3 class="section-title">Anotações</h3>
                                <p class="section-sub">Registros e observações sobre este lead</p>
                            </div>
                            <button v-if="!showAddNote" @click="showAddNote = true" class="btn-primary">
                                <PlusCircle :size="15" />
                                Nova Anotação
                            </button>
                        </div>

                        <Transition name="slide">
                            <div v-if="showAddNote" class="note-form-card">
                                <p class="note-form-label">Nova anotação</p>
                                <textarea
                                    v-model="noteForm.descricao"
                                    rows="4"
                                    placeholder="Digite sua anotação aqui..."
                                    class="note-textarea"
                                />
                                <div class="note-form-actions">
                                    <button @click="showAddNote = false" class="btn-ghost">
                                        <X :size="14" /> Cancelar
                                    </button>
                                    <button
                                        @click="cadastrarAnotacao"
                                        class="btn-primary"
                                        :disabled="!noteForm.descricao"
                                    >
                                        <Save :size="14" /> Salvar
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <div v-if="anotacoes.length > 0" class="notes-list">
                            <div
                                v-for="(anotacao, i) in anotacoes"
                                :key="anotacao.id"
                                class="note-card"
                                :style="{ animationDelay: `${i * 0.05}s` }"
                            >
                                <div class="note-top">
                                    <div class="note-icon-wrap">
                                        <FileText :size="14" />
                                    </div>
                                    <div class="note-actions" v-if="editingNoteId !== anotacao.id">
                                        <button @click="editarAnotacao(anotacao)" class="icon-btn icon-btn--edit" title="Editar">
                                            <Edit :size="13" />
                                        </button>
                                        <button @click="excluirAnotacao(anotacao.id)" class="icon-btn icon-btn--delete" title="Excluir">
                                            <Trash2 :size="13" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="editingNoteId !== anotacao.id" class="note-body">
                                    <p class="note-text">{{ anotacao.descricao }}</p>
                                </div>

                                <div v-else class="note-body">
                                    <textarea
                                        v-model="editNoteForm.descricao"
                                        rows="3"
                                        class="note-textarea"
                                    />
                                    <div class="note-form-actions">
                                        <button @click="cancelarEdicaoAnotacao" class="btn-ghost">
                                            <X :size="14" /> Cancelar
                                        </button>
                                        <button @click="salvarEdicaoAnotacao" class="btn-primary">
                                            <Save :size="14" /> Salvar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!showAddNote" class="empty-tab">
                            <div class="empty-icon"><FileText :size="32" /></div>
                            <p class="empty-title">Nenhuma anotação</p>
                            <p class="empty-sub">Comece adicionando uma observação sobre este lead.</p>
                        </div>
                    </div>

                    <div v-if="activeTab === 'arquivos'" class="tab-section">
                        <div class="section-header">
                            <div>
                                <h3 class="section-title">Arquivos</h3>
                                <p class="section-sub">Documentos e anexos do lead</p>
                            </div>
                            <label class="btn-primary" style="cursor:pointer">
                                <Paperclip :size="15" />
                                Selecionar arquivos
                                <input type="file" multiple class="sr-only" @change="adicionarArquivos" />
                            </label>
                        </div>

                        <div v-if="arquivosPendentes.length > 0" class="pending-card">
                            <div class="pending-header">
                                <span class="pending-title">
                                    <Upload :size="14" />
                                    Pendentes ({{ arquivosPendentes.length }})
                                </span>
                                <div class="pending-actions">
                                    <button @click="arquivosPendentes = []" class="btn-ghost btn-sm">Limpar</button>
                                    <button
                                        @click="salvarArquivos"
                                        class="btn-green"
                                        :disabled="isUploading"
                                    >
                                        <Save :size="14" />
                                        {{ isUploading ? 'Enviando...' : 'Enviar arquivos' }}
                                    </button>
                                </div>
                            </div>

                            <div class="pending-list">
                                <div v-for="arq in arquivosPendentes" :key="arq.id" class="pending-item">
                                    <div class="pending-file-info">
                                        <Paperclip :size="13" class="pending-file-icon" />
                                        <div>
                                            <p class="pending-filename">{{ arq.name }}</p>
                                            <p class="pending-filesize">{{ formatarTamanho(arq.size) }}</p>
                                        </div>
                                    </div>
                                    <button @click="removerArquivoPendente(arq.id)" class="icon-btn icon-btn--delete">
                                        <X :size="13" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="isUploading" class="progress-wrap">
                                <div class="progress-bar">
                                    <div class="progress-fill" :style="{ width: uploadProgress + '%' }" />
                                </div>
                                <span class="progress-label">{{ uploadProgress }}%</span>
                            </div>
                        </div>

                        <div class="files-panel" v-if="arquivos.length > 0">
                            <p class="files-panel-title">
                                <FileText :size="13" />
                                Arquivos salvos
                            </p>
                            <div class="files-table">
                                <div class="files-thead">
                                    <span>Nome</span>
                                    <span>Tamanho</span>
                                    <span class="text-right">Ações</span>
                                </div>
                                <div
                                    v-for="arquivo in arquivos"
                                    :key="arquivo.id"
                                    class="files-row"
                                >
                                    <div class="file-name-cell">
                                        <div class="file-icon-wrap">
                                            <Paperclip :size="13" />
                                        </div>
                                        <span class="file-name">{{ arquivo.nome }}</span>
                                    </div>
                                    <span class="file-size">{{ formatarTamanho(arquivo.tamanho || 0) }}</span>
                                    <div class="file-row-actions">
                                        <a :href="`/storage/${arquivo.local}`" download class="icon-btn icon-btn--download" title="Download">
                                            <Download :size="13" />
                                        </a>
                                        <button @click="excluirArquivo(arquivo.id)" class="icon-btn icon-btn--delete" title="Excluir">
                                            <Trash2 :size="13" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!arquivosPendentes.length" class="empty-tab">
                            <div class="empty-icon"><Paperclip :size="32" /></div>
                            <p class="empty-title">Nenhum arquivo salvo</p>
                            <p class="empty-sub">Selecione arquivos acima para começar.</p>
                        </div>
                    </div>

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
        --accent:   #6d5dfc;
        --accent-h: #7c6efd;
        --glow:     rgba(109, 93, 252, 0.22);
        --green:    #3ecf8e;
        --red:      #f06292;
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

    .loading-state {
        position: relative; z-index: 1;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        min-height: 60vh; gap: 1rem;
    }

    .spinner {
        width: 32px; height: 32px;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.65s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text { font-size: 0.875rem; color: var(--t3); }

    .content {
        position: relative; z-index: 1;
        padding: 2.5rem 2.5rem 3rem;
        width: 100%;
    }

    .breadcrumb {
        display: flex; align-items: center; gap: 0.5rem;
        margin-bottom: 2rem;
    }
    .breadcrumb-link {
        display: inline-flex; align-items: center; gap: 0.35rem;
        font-size: 0.82rem; color: var(--t3); text-decoration: none;
        transition: color 0.2s;
    }
    .breadcrumb-link svg { width: 14px; height: 14px; }
    .breadcrumb-link:hover { color: var(--accent); }
    .breadcrumb-sep { color: var(--t3); font-size: 0.82rem; }
    .breadcrumb-current { font-size: 0.82rem; color: var(--t2); }

    .profile-hero {
        display: flex; align-items: center;
        justify-content: space-between;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .hero-left { display: flex; align-items: center; gap: 1.25rem; }

    .profile-avatar {
        width: 56px; height: 56px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--accent), #9c52f2);
        display: flex; align-items: center; justify-content: center;
        font-family: 'Syne', sans-serif;
        font-size: 1.1rem; font-weight: 700; color: #fff;
        flex-shrink: 0;
    }

    .profile-name {
        font-family: 'Syne', sans-serif;
        font-size: 1.4rem; font-weight: 800;
        color: var(--t1); margin: 0 0 0.35rem;
        letter-spacing: -0.4px;
    }

    .profile-meta { display: flex; flex-wrap: wrap; gap: 1rem; }

    .meta-item {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-size: 0.82rem; color: var(--t2);
    }
    .meta-item svg { width: 13px; height: 13px; }

    .tabs-bar {
        display: flex; align-items: center; gap: 0.25rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.75rem;
    }

    .tab-btn {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.7rem 1.1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem; font-weight: 400;
        color: var(--t3); background: transparent;
        border: none; border-bottom: 2px solid transparent;
        cursor: pointer; transition: color 0.2s, border-color 0.2s;
        margin-bottom: -1px;
    }
    .tab-btn:hover { color: var(--t2); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 500; }

    .tab-badge {
        font-size: 0.68rem; font-weight: 600;
        color: var(--t3); background: rgba(255,255,255,0.05);
        border-radius: 100px; padding: 0.1rem 0.5rem;
    }
    .tab-badge--active { color: var(--accent); background: rgba(109,93,252,0.15); }

    .tab-content { }

    .info-grid {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .info-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.5rem;
    }

    .card-section-title {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.8rem; font-weight: 500;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: var(--t3); margin-bottom: 1.25rem;
    }
    .card-section-title svg { width: 15px; height: 15px; }

    .fields-stack { display: flex; flex-direction: column; gap: 1rem; }

    .edit-field { display: flex; flex-direction: column; gap: 0.4rem; }

    .edit-label {
        font-size: 0.72rem; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase;
        color: var(--t3);
    }

    .edit-input {
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: 9px;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        padding: 0.65rem 0.9rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%; box-sizing: border-box;
    }
    .edit-input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--glow);
    }

    .empty-docs {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 2.5rem 1rem; gap: 0.5rem;
        color: var(--t3); text-align: center;
    }
    .empty-docs svg { width: 32px; height: 32px; opacity: 0.35; }
    .empty-docs p { font-size: 0.82rem; }

    .tab-section { display: flex; flex-direction: column; gap: 1.25rem; }

    .section-header {
        display: flex; align-items: flex-start;
        justify-content: space-between; gap: 1rem;
        flex-wrap: wrap;
    }

    .section-title {
        font-family: 'Syne', sans-serif;
        font-size: 1.1rem; font-weight: 700;
        color: var(--t1); margin: 0 0 0.2rem;
    }
    .section-sub { font-size: 0.8rem; color: var(--t3); }

    .btn-primary {
        display: inline-flex; align-items: center; gap: 0.45rem;
        background: var(--accent); color: #fff;
        border: none; border-radius: 9px;
        padding: 0.6rem 1.1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem; font-weight: 500;
        cursor: pointer; text-decoration: none;
        transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
        white-space: nowrap;
    }
    .btn-primary:hover:not(:disabled) { background: var(--accent-h); box-shadow: 0 0 16px var(--glow); transform: translateY(-1px); }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

    .btn-save {
        display: inline-flex; align-items: center; gap: 0.45rem;
        background: rgba(109,93,252,0.15);
        border: 1px solid rgba(109,93,252,0.35);
        color: var(--accent); border-radius: 9px;
        padding: 0.6rem 1.1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem; font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
    }
    .btn-save:hover { background: rgba(109,93,252,0.25); border-color: var(--accent); }

    .btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.6rem 1rem;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 9px; color: var(--t2);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem; cursor: pointer;
        transition: border-color 0.2s, color 0.2s;
    }
    .btn-ghost:hover { border-color: var(--t2); color: var(--t1); }
    .btn-ghost.btn-sm { padding: 0.45rem 0.8rem; font-size: 0.8rem; }

    .btn-green {
        display: inline-flex; align-items: center; gap: 0.45rem;
        background: rgba(62,207,142,0.15);
        border: 1px solid rgba(62,207,142,0.3);
        color: var(--green); border-radius: 9px;
        padding: 0.55rem 1rem;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem; font-weight: 500;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-green:hover:not(:disabled) { background: rgba(62,207,142,0.25); }
    .btn-green:disabled { opacity: 0.5; cursor: not-allowed; }

    .icon-btn {
        width: 28px; height: 28px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 7px; border: 1px solid var(--border);
        background: transparent; cursor: pointer;
        color: var(--t3); transition: color 0.15s, border-color 0.15s, background 0.15s;
        text-decoration: none;
    }
    .icon-btn--edit:hover { color: var(--accent); border-color: rgba(109,93,252,0.4); background: rgba(109,93,252,0.08); }
    .icon-btn--delete:hover { color: var(--red); border-color: rgba(240,98,146,0.4); background: rgba(240,98,146,0.08); }
    .icon-btn--download:hover { color: var(--green); border-color: rgba(62,207,142,0.4); background: rgba(62,207,142,0.08); }

    .note-form-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.25rem;
        display: flex; flex-direction: column; gap: 0.75rem;
    }
    .note-form-label { font-size: 0.75rem; color: var(--t3); font-weight: 500; letter-spacing: 0.05em; text-transform: uppercase; }
    .note-form-actions { display: flex; justify-content: flex-end; gap: 0.6rem; }

    .note-textarea {
        background: var(--inp-bg);
        border: 1px solid var(--border);
        border-radius: 9px;
        color: var(--t1);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        padding: 0.75rem 1rem;
        outline: none; resize: vertical; width: 100%;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .note-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--glow); }
    .note-textarea::placeholder { color: var(--t3); }

    .notes-list { display: flex; flex-direction: column; gap: 0.75rem; }

    .note-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.1rem 1.25rem;
        animation: fadeRow 0.35s cubic-bezier(0.22,1,0.36,1) both;
        transition: border-color 0.2s;
    }
    .note-card:hover { border-color: rgba(109,93,252,0.3); }

    @keyframes fadeRow {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .note-top {
        display: flex; align-items: center;
        justify-content: space-between;
        margin-bottom: 0.65rem;
    }
    .note-icon-wrap {
        width: 26px; height: 26px;
        border-radius: 7px;
        background: rgba(109,93,252,0.12);
        color: var(--accent);
        display: flex; align-items: center; justify-content: center;
    }
    .note-actions { display: flex; gap: 0.4rem; }
    .note-body { }
    .note-text { font-size: 0.875rem; color: var(--t2); line-height: 1.6; }

    .empty-tab {
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 4rem 2rem; gap: 0.6rem; text-align: center;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
    }
    .empty-icon { color: var(--t3); opacity: 0.4; }
    .empty-title { font-size: 0.95rem; font-weight: 500; color: var(--t2); }
    .empty-sub { font-size: 0.82rem; color: var(--t3); }

    .pending-card {
        background: rgba(109,93,252,0.06);
        border: 1px solid rgba(109,93,252,0.2);
        border-radius: 14px;
        padding: 1.25rem;
    }
    .pending-header {
        display: flex; align-items: center;
        justify-content: space-between; gap: 1rem;
        margin-bottom: 1rem; flex-wrap: wrap;
    }
    .pending-title {
        display: inline-flex; align-items: center; gap: 0.4rem;
        font-size: 0.82rem; font-weight: 500; color: var(--accent);
    }
    .pending-actions { display: flex; gap: 0.5rem; }
    .pending-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .pending-item {
        display: flex; align-items: center;
        justify-content: space-between;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 9px;
        padding: 0.65rem 0.9rem;
    }
    .pending-file-info { display: flex; align-items: center; gap: 0.6rem; }
    .pending-file-icon { color: var(--t3); }
    .pending-filename { font-size: 0.82rem; font-weight: 500; color: var(--t1); }
    .pending-filesize { font-size: 0.72rem; color: var(--t3); }

    .progress-wrap {
        display: flex; align-items: center; gap: 0.75rem;
        margin-top: 1rem;
    }
    .progress-bar {
        flex: 1; height: 4px;
        background: var(--border); border-radius: 100px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%; background: var(--accent);
        border-radius: 100px;
        transition: width 0.3s ease;
    }
    .progress-label { font-size: 0.75rem; color: var(--t2); min-width: 32px; }

    .files-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }
    .files-panel-title {
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.75rem; font-weight: 500;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: var(--t3);
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        margin: 0;
    }
    .files-thead {
        display: grid; grid-template-columns: 1fr 120px 80px;
        padding: 0.6rem 1.25rem;
        background: rgba(0,0,0,0.15);
        border-bottom: 1px solid var(--border);
    }
    .files-thead span {
        font-size: 0.68rem; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase;
        color: var(--t3);
    }
    .text-right { text-align: right; }

    .files-row {
        display: grid; grid-template-columns: 1fr 120px 80px;
        align-items: center;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid rgba(30,40,64,0.5);
        transition: background 0.15s;
    }
    .files-row:last-child { border-bottom: none; }
    .files-row:hover { background: rgba(109,93,252,0.04); }

    .file-name-cell {
        display: flex; align-items: center; gap: 0.65rem;
        min-width: 0;
    }
    .file-icon-wrap {
        width: 28px; height: 28px;
        border-radius: 7px;
        background: rgba(109,93,252,0.1);
        color: var(--accent);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .file-name {
        font-size: 0.85rem; color: var(--t1);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .file-size { font-size: 0.8rem; color: var(--t3); }
    .file-row-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; }

    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); }

    .slide-enter-active, .slide-leave-active { transition: opacity 0.2s, transform 0.2s; }
    .slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }

    @media (max-width: 768px) {
        .content { padding: 1.5rem 1.25rem; }
        .info-grid { grid-template-columns: 1fr; }
        .profile-hero { flex-direction: column; align-items: flex-start; }
        .btn-save { width: 100%; justify-content: center; }
        .files-thead, .files-row { grid-template-columns: 1fr 80px; }
        .file-size { display: none; }
    }
</style>