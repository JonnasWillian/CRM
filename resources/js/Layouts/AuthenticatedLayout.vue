<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, UserCircle, X } from 'lucide-vue-next';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppDropdownSeparador from '@/Components/ui/AppDropdownSeparador.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import { usePrefsLocais } from '@/Composables/usePrefsLocais';
import { useToast } from '@/Composables/useToast';
import NavItens from './NavItens.vue';
import { gruposDeNavegacao } from './navegacao';

/**
 * Casca autenticada: barra lateral (232px; 56px recolhida, preferência
 * guardada no navegador) em telas médias para cima, topo com gaveta
 * <dialog> abaixo disso. Hospeda os toasts, o diálogo de confirmação e
 * converte o `flash` do Inertia em toast.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

const pode = (permissao) => page.props.auth.permissions?.[permissao] === true;
const existe = (rota) => typeof route === 'function' && route().has(rota);
const grupos = computed(() => gruposDeNavegacao({ pode, existe }));

const colapsada = usePrefsLocais('sidebar.colapsada', false);

// ── Gaveta mobile ──
const gavetaAberta = ref(false);
const gaveta = ref(null);
const burger = ref(null);

async function abrirGaveta() {
    gavetaAberta.value = true;
    await nextTick();
    const el = gaveta.value;
    if (!el) return;
    if (typeof el.showModal === 'function') el.showModal();
    else el.setAttribute('open', '');
    document.body.style.overflow = 'hidden';
    el.querySelector('a, button')?.focus();
}

function fecharGaveta() {
    if (!gavetaAberta.value) return;
    const el = gaveta.value;
    if (el && typeof el.close === 'function' && el.open) el.close();
    gavetaAberta.value = false;
    document.body.style.overflow = '';
    burger.value?.$el?.querySelector?.('button')?.focus?.() ?? burger.value?.focus?.();
}

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

// ── Flash → toast ──
const toast = useToast();
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.sucesso) toast.sucesso(flash.sucesso);
        if (flash?.erro) toast.erro(flash.erro);
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex min-h-dvh bg-bg-0">
        <!-- Barra lateral -->
        <aside
            :data-colapsada="colapsada ? 'true' : 'false'"
            class="fixed inset-y-0 left-0 z-40 hidden flex-col border-r border-line bg-bg-1 transition-[width] duration-3 ease-ui md:flex"
            :class="colapsada ? 'w-14' : 'w-[232px]'"
        >
            <div class="flex h-14 items-center border-b border-line" :class="colapsada ? 'justify-center' : 'justify-between pl-4 pr-2'">
                <Link v-if="!colapsada" :href="route('dashboard')" class="font-display text-lg font-bold tracking-tight text-fg-0">
                    UserFlow<span class="text-primary">.</span>
                </Link>
                <IconButton
                    :icone="colapsada ? PanelLeftOpen : PanelLeftClose"
                    :rotulo="colapsada ? 'Expandir menu' : 'Recolher menu'"
                    tamanho="sm"
                    lado-tooltip="right"
                    @click="colapsada = !colapsada"
                />
            </div>

            <nav aria-label="Principal" class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 py-3">
                <NavItens :grupos="grupos" :colapsada="colapsada" />
            </nav>

            <div class="border-t border-line p-2">
                <AppDropdown rotulo="Conta" alinhar="start" lado="top">
                    <template #gatilho="{ alternar, atributos }">
                        <button
                            type="button"
                            v-bind="atributos"
                            class="flex w-full items-center gap-2 rounded p-1.5 text-left transition-colors duration-1 ease-ui hover:bg-bg-2"
                            :class="colapsada ? 'justify-center' : ''"
                            :aria-label="colapsada ? `Conta: ${user.name}` : undefined"
                            @click="alternar"
                        >
                            <AppAvatar :nome="user.name" :tamanho="28" />
                            <span v-if="!colapsada" class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-medium text-fg-0">{{ user.name }}</span>
                                <span class="block truncate text-xs text-fg-2">{{ user.email }}</span>
                            </span>
                            <ChevronsUpDown v-if="!colapsada" :size="14" class="shrink-0 text-fg-2" aria-hidden="true" />
                        </button>
                    </template>
                    <AppDropdownItem rotulo="Perfil e conta" :icone="UserCircle" as="Link" :href="route('profile.edit')" />
                    <AppDropdownSeparador />
                    <AppDropdownItem rotulo="Sair" :icone="LogOut" as="Link" :href="route('logout')" method="post" />
                </AppDropdown>
            </div>
        </aside>

        <!-- Topo mobile -->
        <header class="fixed inset-x-0 top-0 z-40 flex h-14 items-center justify-between border-b border-line bg-bg-1 px-3 md:hidden">
            <Link :href="route('dashboard')" class="font-display text-lg font-bold tracking-tight text-fg-0">UserFlow<span class="text-primary">.</span></Link>
            <IconButton ref="burger" :icone="Menu" rotulo="Abrir menu" :aria-expanded="gavetaAberta" @click="abrirGaveta" />
        </header>

        <dialog
            v-if="gavetaAberta"
            ref="gaveta"
            aria-label="Menu"
            class="m-0 h-dvh max-h-none w-[280px] max-w-[85vw] border-r border-line bg-bg-1 p-0 text-fg-0 shadow-3 backdrop:bg-fg-0/40"
            @keydown.escape.prevent="fecharGaveta"
            @cancel.prevent="fecharGaveta"
            @click="(e) => e.target === gaveta && fecharGaveta()"
        >
            <div class="flex h-full flex-col">
                <div class="flex h-14 items-center justify-between border-b border-line pl-4 pr-2">
                    <span class="font-display text-lg font-bold tracking-tight">UserFlow<span class="text-primary">.</span></span>
                    <IconButton :icone="X" rotulo="Fechar menu" tamanho="sm" @click="fecharGaveta" />
                </div>
                <nav aria-label="Principal" class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 py-3">
                    <NavItens :grupos="grupos" @navegar="fecharGaveta" />
                    <p class="px-2 pb-1 pt-4 text-xs font-medium uppercase tracking-wider text-fg-2">Conta</p>
                    <Link :href="route('profile.edit')" class="flex items-center gap-2.5 rounded px-2 py-2 text-md text-fg-1 hover:bg-bg-2 hover:text-fg-0" @click="fecharGaveta">
                        <UserCircle :size="18" aria-hidden="true" /> Perfil e conta
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-2.5 rounded px-2 py-2 text-left text-md text-fg-1 hover:bg-bg-2 hover:text-fg-0">
                        <LogOut :size="18" aria-hidden="true" /> Sair
                    </Link>
                </nav>
                <div class="flex items-center gap-2 border-t border-line p-3">
                    <AppAvatar :nome="user.name" :tamanho="28" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                        <span class="block truncate text-xs text-fg-2">{{ user.email }}</span>
                    </span>
                </div>
            </div>
        </dialog>

        <main class="min-w-0 flex-1 pt-14 transition-[margin] duration-3 ease-ui md:pt-0" :class="colapsada ? 'md:ml-14' : 'md:ml-[232px]'">
            <slot />
        </main>

        <ToastHost />
        <ConfirmHost />
    </div>
</template>
