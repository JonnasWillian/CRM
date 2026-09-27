<script setup>
    import { computed, ref } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';

    const showingMobileMenu = ref(false);
    const user = computed(() => usePage().props.auth.user);

    const pode = (permissao) => usePage().props.auth.permissions?.[permissao] === true;

    /**
     * A navegação em grupos, declarada UMA vez.
     *
     * Antes os itens estavam duplicados entre a barra lateral e a gaveta
     * mobile, o que já tinha custado: o Pipeline não existia em nenhuma das
     * duas, e as telas novas entraram só numa. Uma lista só torna esse tipo
     * de esquecimento impossível.
     *
     * Os grupos existem porque seis itens soltos exigem ler todos para achar
     * um; três blocos curtos o olho varre.
     */
    const grupos = computed(() => [
        {
            itens: [
                { rota: 'dashboard', rotulo: 'Leads', icone: 'lista' },
                { rota: 'kanban',    rotulo: 'Pipeline', icone: 'quadro' },
                { rota: 'modelosTarefa', rotulo: 'Modelos', icone: 'modelo' },
            ],
        },
        {
            titulo: 'Análise',
            itens: [
                { rota: 'relatorios.perdas', rotulo: 'Por que perdemos', icone: 'queda', quando: pode('leads.view-all') },
            ],
        },
        {
            titulo: 'Configuração',
            itens: [
                { rota: 'configuracoes.funis', rotulo: 'Funis', icone: 'funil', quando: pode('configuracoes.manage') },
                { rota: 'configuracoes.motivosPerda', rotulo: 'Motivos de perda', icone: 'alerta', quando: pode('configuracoes.manage') },
            ],
        },
    ].map(g => ({ ...g, itens: g.itens.filter(i => i.quando !== false) }))
     .filter(g => g.itens.length));

    // Traçados num mapa, não repetidos no template: o mesmo ícone aparece na
    // barra e na gaveta, e manter dois SVGs iguais em sincronia é trabalho
    // que ninguém lembra de fazer.
    const tracos = {
        lista:  'M4 6h16M4 12h16M4 18h10',
        quadro: 'M4 5h5v14H4zM15 5h5v9h-5z',
        modelo: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        queda:  'M3 7l6 6 4-4 8 8m0 0h-5m5 0v-5',
        funil:  'M4 6h10M4 12h7M4 18h4M17 4v6m0 0l-2.5-2.5M17 10l2.5-2.5',
        alerta: 'M12 9v4m0 4h.01M10.3 3.9l-8 14A2 2 0 004 21h16a2 2 0 001.7-3.1l-8-14a2 2 0 00-3.4 0z',
        perfil: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        sair:   'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
    };

    const iniciais = (nome) =>
        nome?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() || '?';
</script>

<template>
    <div class="ly">
        <aside class="ly-side">
            <Link :href="route('dashboard')" class="ly-logo">UserFlow<span>.</span></Link>

            <nav class="ly-nav">
                <template v-for="(g, gi) in grupos" :key="gi">
                    <p v-if="g.titulo" class="ly-grupo">{{ g.titulo }}</p>
                    <Link
                        v-for="i in g.itens"
                        :key="i.rota"
                        :href="route(i.rota)"
                        class="ly-item"
                        :class="{ 'is-on': route().current(i.rota) }"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path :d="tracos[i.icone]" />
                        </svg>
                        <span>{{ i.rotulo }}</span>
                    </Link>
                </template>
            </nav>

            <div class="ly-foot">
                <Link :href="route('profile.edit')" class="ly-eu" :class="{ 'is-on': route().current('profile.edit') }">
                    <span class="ly-av">{{ iniciais(user.name) }}</span>
                    <span class="ly-quem">
                        <b>{{ user.name }}</b>
                        <i>{{ user.email }}</i>
                    </span>
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="ly-sair" title="Sair" aria-label="Sair">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path :d="tracos.sair" />
                    </svg>
                </Link>
            </div>
        </aside>

        <header class="ly-topo">
            <Link :href="route('dashboard')" class="ly-logo">UserFlow<span>.</span></Link>
            <button class="ly-burger" :aria-expanded="showingMobileMenu" aria-label="Menu" @click="showingMobileMenu = !showingMobileMenu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2">
                    <path v-if="!showingMobileMenu" d="M4 6h16M4 12h16M4 18h16" />
                    <path v-else d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <Transition name="ly-gaveta">
            <div v-if="showingMobileMenu" class="ly-overlay" @click.self="showingMobileMenu = false">
                <nav class="ly-painel">
                    <template v-for="(g, gi) in grupos" :key="gi">
                        <p v-if="g.titulo" class="ly-grupo">{{ g.titulo }}</p>
                        <Link
                            v-for="i in g.itens"
                            :key="i.rota"
                            :href="route(i.rota)"
                            class="ly-item"
                            :class="{ 'is-on': route().current(i.rota) }"
                            @click="showingMobileMenu = false"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="tracos[i.icone]" />
                            </svg>
                            <span>{{ i.rotulo }}</span>
                        </Link>
                    </template>

                    <p class="ly-grupo">Conta</p>
                    <Link :href="route('profile.edit')" class="ly-item" @click="showingMobileMenu = false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="tracos.perfil" /></svg>
                        <span>Perfil</span>
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="ly-item ly-item--sair">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path :d="tracos.sair" /></svg>
                        <span>Sair</span>
                    </Link>
                </nav>
            </div>
        </Transition>

        <main class="ly-main"><slot /></main>
    </div>
</template>

<style scoped>
    .ly { display: flex; min-height: 100vh; background: var(--bg-0); font-family: var(--font-body); }

    /* ── Barra lateral ─────────────────────────────── */
    .ly-side {
        width: var(--sidebar-w); position: fixed; inset: 0 auto 0 0; z-index: 40;
        background: var(--bg-1); border-right: 1px solid var(--line);
        display: flex; flex-direction: column; padding: 18px 10px;
    }

    .ly-logo {
        font-family: var(--font-display); font-size: 17px; font-weight: 800;
        letter-spacing: -.4px; color: var(--fg-0); text-decoration: none;
        padding: 0 8px 16px; border-bottom: 1px solid var(--line); margin-bottom: 10px;
    }
    .ly-logo span { color: var(--accent); }

    .ly-nav { flex: 1; display: flex; flex-direction: column; gap: 1px; }

    .ly-grupo {
        font-size: var(--fs-xs); letter-spacing: .09em; text-transform: uppercase;
        color: var(--fg-2); padding: 14px 8px 6px; font-weight: 500;
    }

    .ly-item {
        position: relative; display: flex; align-items: center; gap: 9px;
        padding: 7px 8px; border-radius: var(--r-2);
        color: var(--fg-1); font-size: 13.5px; text-decoration: none;
        border: 0; background: transparent; width: 100%; text-align: left;
        cursor: pointer; font-family: inherit;
        transition: background var(--d-1) var(--e), color var(--d-1) var(--e);
    }
    .ly-item svg { width: 15px; height: 15px; stroke-width: 1.75; flex-shrink: 0; }
    .ly-item:hover { background: var(--bg-2); color: var(--fg-0); }
    .ly-item.is-on { background: var(--bg-2); color: var(--fg-0); }

    /*
     * Item ativo é um filete de 2px, não um bloco pintado.
     * Bloco pintado compete com o conteúdo pela atenção; o filete diz
     * "você está aqui" e se cala.
     */
    .ly-item.is-on::before {
        content: ""; position: absolute; left: -10px; top: 50%;
        transform: translateY(-50%); width: 2px; height: 16px;
        background: var(--accent); border-radius: 0 2px 2px 0;
    }
    .ly-item--sair { color: var(--danger); }

    /* ── Rodapé ────────────────────────────────────── */
    .ly-foot {
        border-top: 1px solid var(--line); padding-top: 12px; margin-top: 8px;
        display: flex; align-items: center; gap: 6px;
    }
    .ly-eu {
        flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px;
        padding: 5px; border-radius: var(--r-2); text-decoration: none;
        transition: background var(--d-1) var(--e);
    }
    .ly-eu:hover, .ly-eu.is-on { background: var(--bg-2); }
    .ly-av {
        width: 28px; height: 28px; border-radius: 7px; flex-shrink: 0;
        background: var(--accent-dim); color: var(--accent-hi);
        display: grid; place-items: center;
        font-family: var(--font-display); font-size: 11px; font-weight: 700;
    }
    .ly-quem { min-width: 0; line-height: 1.25; }
    .ly-quem b, .ly-quem i {
        display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .ly-quem b { font-size: 12px; font-weight: 500; color: var(--fg-0); }
    .ly-quem i { font-size: var(--fs-xs); color: var(--fg-2); font-style: normal; }

    .ly-sair {
        width: 30px; height: 30px; flex-shrink: 0; border-radius: var(--r-2);
        border: 1px solid var(--line); background: transparent; color: var(--fg-2);
        display: grid; place-items: center; cursor: pointer;
        transition: color var(--d-1) var(--e), border-color var(--d-1) var(--e);
    }
    .ly-sair svg { width: 15px; height: 15px; stroke-width: 1.75; }
    .ly-sair:hover { color: var(--danger); border-color: var(--danger); }

    /* ── Conteúdo ──────────────────────────────────── */
    .ly-main { margin-left: var(--sidebar-w); flex: 1; min-width: 0; }

    /* ── Mobile ────────────────────────────────────── */
    .ly-topo {
        display: none; position: fixed; inset: 0 0 auto; z-index: 50; height: 54px;
        background: color-mix(in srgb, var(--bg-0) 92%, transparent);
        backdrop-filter: blur(10px); border-bottom: 1px solid var(--line);
        padding: 0 16px; align-items: center; justify-content: space-between;
    }
    .ly-topo .ly-logo { padding: 0; border: 0; margin: 0; }

    .ly-burger {
        width: 34px; height: 34px; border: 1px solid var(--line); background: transparent;
        border-radius: var(--r-2); color: var(--fg-1); display: grid; place-items: center; cursor: pointer;
    }
    .ly-burger svg { width: 18px; height: 18px; }

    .ly-overlay {
        position: fixed; inset: 0; z-index: 49;
        background: rgba(0,0,0,.6); backdrop-filter: blur(3px);
    }
    .ly-painel {
        position: absolute; inset: 54px 0 auto 0; padding: 10px;
        background: var(--bg-1); border-bottom: 1px solid var(--line);
        display: flex; flex-direction: column; gap: 1px;
    }

    .ly-gaveta-enter-active, .ly-gaveta-leave-active { transition: opacity var(--d-2) var(--e); }
    .ly-gaveta-enter-from, .ly-gaveta-leave-to { opacity: 0; }

    @media (max-width: 860px) {
        .ly-side { display: none; }
        .ly-topo { display: flex; }
        .ly-main { margin-left: 0; padding-top: 54px; }
    }
</style>
