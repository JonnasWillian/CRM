<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Perfil — UserFlow" />

    <AuthenticatedLayout>
        <div class="pf-page">
            <header class="pf-topbar">
                <h1 class="pf-title">Perfil <span class="pf-accent">e conta</span></h1>
                <p class="pf-sub">Seus dados de acesso e as configurações da sua conta.</p>
            </header>

            <div class="pf-cards">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
                <UpdatePasswordForm />
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<!--
    Bloco NÃO escopado, de propósito.

    As três seções do perfil são componentes separados e precisam dos mesmos
    estilos de campo e botão. Estilo escopado não atravessa componente, então as
    alternativas eram duplicar o CSS três vezes ou juntar tudo num arquivo só.
    Um bloco global com prefixo `pf-` em toda classe resolve sem nenhum dos dois
    custos — e o prefixo torna a colisão com o resto do app impossível.

    Os tokens são os mesmos de AuthenticatedLayout e das telas de Funis,
    Motivos de perda e Por que perdemos.
-->
<style>
    .pf-page {
        --surface: #13192a;
        --border:  #1e2840;
        --accent:  #6d5dfc;
        --t1:      #eaedf5;
        --t2:      #8892ab;
        --t3:      #4a5470;
        --perigo:  #f06292;

        font-family: 'DM Sans', sans-serif;
        padding: 2rem 2.25rem 4rem;
        color: var(--t1);
        min-height: 100vh;
    }

    .pf-topbar { margin-bottom: 1.5rem; }
    .pf-title { font-family: 'Syne', sans-serif; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
    .pf-accent { color: var(--accent); }
    /* Só o subtítulo tem medida de leitura, igual às telas de Funis,
       Motivos de perda e Por que perdemos. */
    .pf-sub { font-size: 0.85rem; color: var(--t2); margin-top: 0.25rem; max-width: 62ch; }

    /* Sem max-width: os cards ocupam a largura do conteúdo, como em todas as
       outras páginas. Limitar a coluna aqui era o que deixava a tela ocupando
       metade do espaço e destoando do resto. */
    .pf-cards { display: flex; flex-direction: column; gap: 1rem; }

    .pf-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.2rem 1.3rem;
    }
    .pf-card--perigo { border-color: rgba(240, 98, 146, 0.3); }

    .pf-card-titulo { font-family: 'Syne', sans-serif; font-size: 1.02rem; font-weight: 700; }
    .pf-card-desc { font-size: 0.8rem; color: var(--t2); margin-top: 0.3rem; line-height: 1.55; }

    .pf-form { display: flex; flex-direction: column; gap: 0.9rem; margin-top: 1.1rem; }
    .pf-campo { display: flex; flex-direction: column; gap: 0.35rem; }
    .pf-label { font-size: 0.76rem; color: var(--t2); }

    .pf-input {
        width: 100%;
        background: rgba(13, 17, 23, 0.7);
        border: 1px solid var(--border);
        border-radius: 9px;
        padding: 0.55rem 0.75rem;
        color: var(--t1);
        font-size: 0.85rem;
        font-family: inherit;
        outline: none;
        transition: border-color 0.15s;
    }
    .pf-input:focus { border-color: var(--accent); }
    .pf-input::placeholder { color: var(--t3); }

    .pf-erro { font-size: 0.74rem; color: #fca5a5; }

    .pf-aviso {
        font-size: 0.78rem;
        color: var(--t2);
        background: rgba(245, 158, 11, 0.08);
        border: 1px solid rgba(245, 158, 11, 0.28);
        border-radius: 9px;
        padding: 0.6rem 0.75rem;
        line-height: 1.55;
    }
    .pf-link {
        background: none;
        border: none;
        padding: 0;
        color: var(--accent);
        font: inherit;
        cursor: pointer;
        text-decoration: underline;
    }
    .pf-ok { font-size: 0.78rem; color: #34d399; }

    .pf-acoes { display: flex; align-items: center; gap: 0.7rem; margin-top: 0.25rem; }

    .pf-btn-primary {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: var(--accent); color: #fff; border: none; border-radius: 9px;
        padding: 0.5rem 0.95rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }
    .pf-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

    .pf-btn-ghost {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: transparent; color: var(--t2); border: 1px solid var(--border);
        border-radius: 9px; padding: 0.5rem 0.95rem; font-size: 0.82rem;
        font-family: inherit; cursor: pointer;
    }
    .pf-btn-ghost:hover { color: var(--t1); }

    /* Na página, o destrutivo é discreto: ele só ABRE a confirmação. O botão
       que de fato apaga, dentro do diálogo, é o sólido. */
    .pf-btn-perigo {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: rgba(240, 98, 146, 0.1); color: var(--perigo);
        border: 1px solid rgba(240, 98, 146, 0.35); border-radius: 9px;
        padding: 0.5rem 0.95rem; font-size: 0.82rem; font-family: inherit; cursor: pointer;
    }
    .pf-btn-perigo:hover { background: rgba(240, 98, 146, 0.18); }

    .pf-btn-perigo--solido {
        background: var(--perigo); color: #fff; border-color: var(--perigo);
    }
    .pf-btn-perigo--solido:hover { background: #ec407a; }
    .pf-btn-perigo--solido:disabled { opacity: 0.5; cursor: not-allowed; }

    /* ── Diálogo de confirmação ── */
    .pf-overlay {
        position: fixed; inset: 0; z-index: 90;
        background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .pf-dialogo {
        width: 100%; max-width: 440px;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 14px; padding: 1.2rem 1.3rem; color: var(--t1);
    }
    .pf-dialogo-acoes { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.1rem; }

    .pf-fade-enter-active, .pf-fade-leave-active { transition: opacity 0.18s; }
    .pf-fade-enter-from, .pf-fade-leave-to { opacity: 0; }

    @media (max-width: 768px) {
        .pf-page { padding: 1.25rem 1rem 3rem; }
    }
</style>
