<script setup>
    import { ref, computed } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';

    const showingMobileMenu = ref(false);
    const user = computed(() => usePage().props.auth.user);

    const getInitials = (name) => {
        return name?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?';
    };
</script>

<template>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <Link :href="route('dashboard')" class="logo-link">
                    UserFlow<span class="logo-dot">.</span>
                </Link>
            </div>

            <nav class="sidebar-nav">
                <Link :href="route('dashboard')" class="nav-item" :class="{ active: route().current('dashboard') }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Lista de Leads</span>
                </Link>

                <Link :href="route('profile.edit')" class="nav-item" :class="{ active: route().current('profile.edit') }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>Perfil</span>
                </Link>
            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar">{{ getInitials(user.name) }}</div>
                    <div class="user-info">
                        <p class="user-name">{{ user.name }}</p>
                        <p class="user-email">{{ user.email }}</p>
                    </div>
                </div>
                <Link :href="route('logout')" method="post" as="button" class="logout-btn" title="Sair">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </Link>
            </div>
        </aside>

        <header class="mobile-topbar">
            <Link :href="route('dashboard')" class="logo-link">
                UserFlow<span class="logo-dot">.</span>
            </Link>
            <button class="hamburger" @click="showingMobileMenu = !showingMobileMenu">
                <svg v-if="!showingMobileMenu" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </header>

        <Transition name="drawer">
            <div v-if="showingMobileMenu" class="mobile-drawer" @click.self="showingMobileMenu = false">
                <div class="drawer-panel">
                    <nav class="drawer-nav">
                        <Link :href="route('dashboard')" class="nav-item" :class="{ active: route().current('dashboard') }" @click="showingMobileMenu = false">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Dashboard</span>
                        </Link>
                        <Link :href="route('profile.edit')" class="nav-item" @click="showingMobileMenu = false">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>Perfil</span>
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="nav-item logout-mobile">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span>Sair</span>
                        </Link>
                    </nav>
                </div>
            </div>
        </Transition>

        <div class="main-content">
            <slot />
        </div>
    </div>
</template>

<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body, #app {
        background: #0d1117 !important;
        min-height: 100vh;
        height: 100%;
    }
</style>

<style scoped>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap');

    .app-shell {
        --bg:       #0d1117;
        --surface:  #13192a;
        --border:   #1e2840;
        --accent:   #6d5dfc;
        --accent-h: #7c6efd;
        --glow:     rgba(109, 93, 252, 0.22);
        --t1:       #eaedf5;
        --t2:       #8892ab;
        --t3:       #4a5470;
        --sidebar-w: 220px;
        font-family: 'DM Sans', sans-serif;
        display: flex;
        min-height: 100vh;
        background: var(--bg);
    }

    /* ── Sidebar ─── */
    .sidebar {
        width: var(--sidebar-w);
        min-height: 100vh;
        background: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 40;
        padding: 1.75rem 1rem;
    }

    .sidebar-logo {
        padding: 0 0.5rem 2rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }

    .logo-link {
        font-family: 'Syne', sans-serif;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--t1);
        text-decoration: none;
        letter-spacing: -0.4px;
    }

    .logo-dot { color: var(--accent); }

    .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.75rem;
        border-radius: 10px;
        color: var(--t2);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 400;
        transition: background 0.15s, color 0.15s;
        border: none;
        background: transparent;
        cursor: pointer;
        width: 100%;
        text-align: left;
    }

    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }

    .nav-item:hover {
        background: rgba(109, 93, 252, 0.1);
        color: var(--t1);
    }

    .nav-item.active {
        background: rgba(109, 93, 252, 0.15);
        color: var(--accent);
        font-weight: 500;
    }

    .sidebar-footer {
        border-top: 1px solid var(--border);
        padding-top: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .user-card {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex: 1;
        min-width: 0;
    }

    .user-avatar {
        width: 34px;
        height: 34px;
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

    .user-info { min-width: 0; }

    .user-name {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--t1);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-email {
        font-size: 0.7rem;
        color: var(--t3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .logout-btn {
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
        flex-shrink: 0;
        transition: color 0.2s, border-color 0.2s;
        text-decoration: none;
    }
    .logout-btn svg { width: 15px; height: 15px; }
    .logout-btn:hover { color: #f06292; border-color: rgba(240,98,146,0.4); }

    /* ── Main content ─── */
    .main-content {
        margin-left: var(--sidebar-w);
        flex: 1;
        min-height: 100vh;
    }

    /* ── Mobile topbar ─── */
    .mobile-topbar {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        height: 56px;
        background: rgba(13,17,23,0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border);
        padding: 0 1.25rem;
        align-items: center;
        justify-content: space-between;
    }

    .hamburger {
        width: 36px;
        height: 36px;
        border: 1px solid var(--border);
        background: transparent;
        border-radius: 8px;
        color: var(--t2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .hamburger svg { width: 18px; height: 18px; }

    /* ── Mobile drawer ─── */
    .mobile-drawer {
        position: fixed;
        inset: 0;
        z-index: 49;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
    }

    .drawer-panel {
        position: absolute;
        top: 56px;
        left: 0;
        right: 0;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 1rem;
    }

    .drawer-nav { display: flex; flex-direction: column; gap: 0.25rem; }

    .logout-mobile { color: #f06292 !important; }

    .drawer-enter-active, .drawer-leave-active { transition: opacity 0.2s; }
    .drawer-enter-from, .drawer-leave-to { opacity: 0; }


    @media (max-width: 768px) {
        .sidebar { display: none; }
        .mobile-topbar { display: flex; }
        .main-content { margin-left: 0; padding-top: 56px; }
    }
</style>