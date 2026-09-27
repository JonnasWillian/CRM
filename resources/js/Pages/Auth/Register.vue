<script setup>
    import InputError from '@/Components/InputError.vue';
    import TextInput from '@/Components/TextInput.vue';
    import { Head, Link, useForm } from '@inertiajs/vue3';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = () => {
        form.post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };
</script>

<template>
    <Head title="Criar conta — UserFlow" />

    <div class="page">
        <div class="dot-grid" aria-hidden="true" />
        <div class="glow-blob" aria-hidden="true" />

        <nav class="navbar">
            <span class="nav-logo">UserFlow<span class="dot">.</span></span>
            <div class="nav-actions">
                <Link :href="route('login')" class="nav-link">Já tem uma conta? <strong>Entrar</strong></Link>
            </div>
        </nav>

        <main class="main">
            <div class="left-panel">
                <div class="brand-badge">Plataforma de gestão</div>
                <h1 class="brand-headline">
                    Comece a gerenciar com
                    <span class="highlight">simplicidade</span>
                </h1>
                <p class="brand-desc">
                    Crie sua conta gratuitamente e tenha acesso a uma plataforma
                    moderna para gerenciar usuários, permissões e acessos.
                </p>

                <div class="features">
                    <div class="feature-pill">
                        <span class="pill-icon">＋</span>
                        Gestão Simplificada
                    </div>
                    <div class="feature-pill">
                        <span class="pill-icon">◎</span>
                        Controle de Acesso
                    </div>
                    <div class="feature-pill">
                        <span class="pill-icon">⊞</span>
                        Análises Detalhadas
                    </div>
                </div>

                <div class="stats">
                    <div class="stat">
                        <span class="stat-val">+10k</span>
                        <span class="stat-lbl">Usuários Gerenciados</span>
                    </div>
                    <div class="stat-sep" />
                    <div class="stat">
                        <span class="stat-val">99.9%</span>
                        <span class="stat-lbl">Uptime Garantido</span>
                    </div>
                    <div class="stat-sep" />
                    <div class="stat">
                        <span class="stat-val">24/7</span>
                        <span class="stat-lbl">Suporte Dedicado</span>
                    </div>
                </div>
            </div>

            <div class="right-panel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Criar conta</h2>
                        <p class="card-subtitle">Preencha os dados para começar</p>
                    </div>

                    <form @submit.prevent="submit" class="form">
                        <div class="field">
                            <label for="name" class="label">Nome</label>
                            <TextInput
                                id="name"
                                type="text"
                                v-model="form.name"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Seu nome completo"
                                class="input"
                            />
                            <InputError :message="form.errors.name" class="err" />
                        </div>

                        <div class="field">
                            <label for="email" class="label">E-mail</label>
                            <TextInput
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autocomplete="username"
                                placeholder="seu@email.com"
                                class="input"
                            />
                            <InputError :message="form.errors.email" class="err" />
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label for="password" class="label">Senha</label>
                                <TextInput
                                    id="password"
                                    type="password"
                                    v-model="form.password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    class="input"
                                />
                                <InputError :message="form.errors.password" class="err" />
                            </div>

                            <div class="field">
                                <label for="password_confirmation" class="label">Confirmar senha</label>
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    class="input"
                                />
                                <InputError :message="form.errors.password_confirmation" class="err" />
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="btn-submit"
                            :disabled="form.processing"
                        >
                            <span v-if="!form.processing">Criar conta</span>
                            <span v-else class="dots"><i /><i /><i /></span>
                        </button>

                        <p class="login-hint">
                            Já tem uma conta?
                            <Link :href="route('login')" class="login-link">Entrar</Link>
                        </p>
                    </form>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
    body, #app {
        background: #0d1117 !important;
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }
</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap');

    .page {
        font-family: 'DM Sans', sans-serif;
        background: var(--bg);
        min-height: 100vh;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .dot-grid {
        position: fixed;
        inset: 0;
        background-image: radial-gradient(circle, #1c2540 1px, transparent 1px);
        background-size: 30px 30px;
        opacity: 0.55;
        pointer-events: none;
        z-index: 0;
    }

    .glow-blob {
        position: fixed;
        top: -200px;
        left: 30%;
        width: 700px;
        height: 700px;
        background: radial-gradient(circle, rgba(109,93,252,0.18) 0%, transparent 70%);
        pointer-events: none;
        z-index: 0;
    }

    .navbar {
        position: relative;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.2rem 2rem;
        border-bottom: 1px solid var(--border);
        background: rgba(13, 17, 23, 0.85);
        backdrop-filter: blur(12px);
    }

    .nav-logo {
        font-family: 'Syne', sans-serif;
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--t1);
        letter-spacing: -0.4px;
    }

    .dot { color: var(--accent); }

    .nav-link {
        font-size: 0.875rem;
        color: var(--t2);
        text-decoration: none;
        transition: color 0.2s;
    }
    .nav-link strong { color: var(--accent); font-weight: 500; }
    .nav-link:hover { color: var(--t1); }

    .main {
        position: relative;
        z-index: 1;
        flex: 1;
        display: grid;
        grid-template-columns: 1fr 460px;
        max-width: 1100px;
        width: 100%;
        margin: 0 auto;
        padding: 0 3rem;
        align-items: center;
        gap: 5rem;
    }

    .left-panel {
        animation: fadeUp 0.6s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .brand-badge {
        display: inline-flex;
        align-items: center;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--accent);
        background: rgba(109, 93, 252, 0.12);
        border: 1px solid rgba(109, 93, 252, 0.25);
        border-radius: 100px;
        padding: 0.35rem 0.9rem;
        margin-bottom: 1.5rem;
    }

    .brand-headline {
        font-family: 'Syne', sans-serif;
        font-size: clamp(1.75rem, 2.4vw, 2.25rem);
        font-weight: 800;
        color: var(--t1);
        line-height: 1.15;
        letter-spacing: -1px;
        margin: 0 0 1.25rem;
    }

    .highlight { color: var(--accent); }

    .brand-desc {
        font-size: 1rem;
        color: var(--t2);
        line-height: 1.7;
        max-width: 440px;
        margin: 0 0 2.5rem;
        font-weight: 300;
    }

    .features {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 3rem;
    }

    .feature-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.9rem;
        color: var(--t2);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0.65rem 1.1rem;
        width: fit-content;
        transition: border-color 0.2s, color 0.2s;
    }
    .feature-pill:hover { border-color: var(--accent); color: var(--t1); }
    .pill-icon { color: var(--accent); font-size: 1rem; }

    .stats {
        display: flex;
        align-items: center;
        gap: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border);
    }

    .stat { display: flex; flex-direction: column; gap: 0.2rem; }

    .stat-val {
        font-family: 'Syne', sans-serif;
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--t1);
        line-height: 1;
    }

    .stat-lbl { font-size: 0.75rem; color: var(--t3); }
    .stat-sep { width: 1px; height: 36px; background: var(--border); }

    .right-panel {
        animation: fadeUp 0.6s 0.1s cubic-bezier(0.22,1,0.36,1) both;
    }

    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 22px;
        padding: 2.5rem;
        box-shadow:
            0 0 0 1px rgba(255,255,255,0.035) inset,
            0 40px 100px rgba(0,0,0,0.55),
            0 0 80px var(--glow);
    }

    .card-header { margin-bottom: 1.75rem; }

    .card-title {
        font-family: 'Syne', sans-serif;
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--t1);
        letter-spacing: -0.5px;
        margin: 0 0 0.4rem;
    }

    .card-subtitle { font-size: 0.9rem; color: var(--t2); margin: 0; font-weight: 300; }

    .form { display: flex; flex-direction: column; gap: 1.25rem; }

    .field { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }

    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .label {
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--t3);
    }

    :deep(.input) {
        width: 100% !important;
        background: var(--inp-bg) !important;
        border: 1px solid var(--border) !important;
        border-radius: 10px !important;
        color: var(--t1) !important;
        font-family: 'DM Sans', sans-serif !important;
        font-size: 0.95rem !important;
        padding: 0.8rem 1rem !important;
        transition: border-color 0.2s, box-shadow 0.2s !important;
        outline: none !important;
        box-shadow: none !important;
    }
    :deep(.input::placeholder) { color: var(--t3) !important; }
    :deep(.input:focus) {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px var(--glow) !important;
    }

    .err { font-size: 0.78rem; color: #f06292; }

    .btn-submit {
        width: 100%;
        padding: 0.9rem 1rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
        margin-top: 0.25rem;
    }
    .btn-submit:hover:not(:disabled) {
        background: var(--accent-h);
        box-shadow: 0 0 24px var(--glow);
        transform: translateY(-1px);
    }
    .btn-submit:active:not(:disabled) { transform: translateY(0); }
    .btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

    .dots { display: inline-flex; gap: 5px; align-items: center; justify-content: center; }
    .dots i { display: block; width: 6px; height: 6px; background: #fff; border-radius: 50%; animation: bounce 1.1s infinite ease-in-out; font-style: normal; }
    .dots i:nth-child(2) { animation-delay: 0.18s; }
    .dots i:nth-child(3) { animation-delay: 0.36s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0.65); opacity: 0.45; }
        40%           { transform: scale(1);    opacity: 1; }
    }

    .login-hint {
        text-align: center;
        font-size: 0.85rem;
        color: var(--t3);
        margin: 0;
    }

    .login-link {
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s;
    }
    .login-link:hover { color: var(--accent-h); }

    @media (max-width: 900px) {
        .navbar { padding: 1.2rem 1.5rem; }

        .main {
            grid-template-columns: 1fr;
            padding: 2rem 1.5rem;
            gap: 2.5rem;
            align-items: start;
        }

        .brand-headline { font-size: 1.75rem; letter-spacing: -0.5px; }
        .brand-desc { font-size: 0.9rem; margin-bottom: 1.75rem; }
        .features { gap: 0.6rem; margin-bottom: 2rem; }
        .feature-pill { font-size: 0.85rem; padding: 0.55rem 0.9rem; }
        .stats { gap: 1.25rem; padding-top: 1.5rem; }
        .stat-val { font-size: 1.2rem; }
        .card { padding: 2rem 1.5rem; border-radius: 16px; }
        .card-title { font-size: 1.4rem; }
    }

    @media (max-width: 480px) {
        .navbar { padding: 1rem 1.25rem; }
        .nav-logo { font-size: 1.1rem; }

        .main { padding: 1.5rem 1.25rem; gap: 2rem; }

        .brand-badge { font-size: 0.7rem; padding: 0.3rem 0.75rem; }
        .brand-headline { font-size: 1.5rem; }
        .stats { flex-wrap: wrap; gap: 1rem 1.5rem; }
        .stat-sep { display: none; }

        .field-row { grid-template-columns: 1fr; }

        .card { padding: 1.5rem 1.25rem; border-radius: 14px; }
        .card-title { font-size: 1.25rem; }
        .card-subtitle { font-size: 0.85rem; }
    }
</style>