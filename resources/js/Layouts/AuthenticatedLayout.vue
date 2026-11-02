<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, RotateCcw, UserCircle, X } from 'lucide-vue-next';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppDropdownSeparador from '@/Components/ui/AppDropdownSeparador.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import PerdaHost from '@/Components/leads/PerdaHost.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SeletorDeTema from '@/Components/ui/SeletorDeTema.vue';
import ToastHost from '@/Components/ui/ToastHost.vue';
import BoasVindasModal from '@/Components/tour/BoasVindasModal.vue';
import TourHost from '@/Components/tour/TourHost.vue';
import { useTour } from '@/Composables/useTour';
import { usePrefsLocais } from '@/Composables/usePrefsLocais';
import { useToast } from '@/Composables/useToast';
import NavItens from './NavItens.vue';
import { gruposDeNavegacao } from './navegacao';
import { faixaDaAssinatura } from '@/utils/faixaDaAssinatura';

/**
 * Casca autenticada: barra lateral sobre o fundo (232px; 56px recolhida,
 * preferência guardada no navegador) em telas médias para cima, e o
 * conteúdo numa folha arredondada ao lado. O tema fica no menu da conta.
 * Abaixo de `md`, topo com gaveta <dialog>. Hospeda os toasts, o
 * diálogo de confirmação e converte o `flash` do Inertia em toast.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);
const nomeDoProduto = computed(() => page.props.app?.nome ?? 'CRM');
const faixaAssinatura = computed(() => faixaDaAssinatura(page.props.assinatura));

const pode = (permissao) => page.props.auth.permissions?.[permissao] === true;
const existe = (rota) => typeof route === 'function' && route().has(rota);
const grupos = computed(() => gruposDeNavegacao({ pode, existe }));

const colapsada = usePrefsLocais('sidebar.colapsada', false);

// Passo a passo do primeiro acesso: boas-vindas, balões e "Refazer" no menu da conta.
const tour = useTour();

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
            class="fixed inset-y-0 left-0 z-40 hidden flex-col bg-bg-0 transition-[width] duration-3 ease-ui md:flex"
            :class="colapsada ? 'w-14' : 'w-[232px]'"
        >
            <div class="flex h-16 items-center" :class="colapsada ? 'justify-center' : 'justify-between pl-5 pr-2'">
                <Link v-if="!colapsada" :href="route('dashboard')" class="font-display text-xl font-bold tracking-tight text-fg-0">
                    {{ nomeDoProduto }}<span class="text-primary">.</span>
                </Link>
                <IconButton
                    :icone="colapsada ? PanelLeftOpen : PanelLeftClose"
                    :rotulo="colapsada ? 'Expandir menu' : 'Recolher menu'"
                    tamanho="sm"
                    lado-tooltip="right"
                    @click="colapsada = !colapsada"
                />
            </div>

            <nav aria-label="Principal" class="flex flex-1 flex-col gap-1 overflow-y-auto px-2.5 py-2">
                <NavItens :grupos="grupos" :colapsada="colapsada" />
            </nav>

            <div class="p-2.5">
                <AppDropdown rotulo="Conta" alinhar="start" lado="top" bloco>
                    <template #gatilho="{ alternar, atributos }">
                        <button
                            type="button"
                            v-bind="atributos"
                            class="flex w-full items-center gap-2.5 rounded-lg bg-bg-1 p-2 text-left shadow-2 transition-colors duration-1 ease-ui hover:bg-bg-2"
                            :class="colapsada ? 'justify-center' : ''"
                            :aria-label="colapsada ? `Conta: ${user.name}` : undefined"
                            @click="alternar"
                        >
                            <AppAvatar :nome="user.name" :tamanho="32" />
                            <span v-if="!colapsada" class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-sm font-medium text-fg-0">{{ user.name }}</span>
                                <span class="block truncate text-xs text-fg-2">{{ user.email }}</span>
                            </span>
                            <ChevronsUpDown v-if="!colapsada" :size="14" class="shrink-0 text-fg-2" aria-hidden="true" />
                        </button>
                    </template>
                    <div class="px-2.5 pb-2 pt-1.5">
                        <p class="mb-1.5 text-sm font-medium text-fg-2">Tema</p>
                        <SeletorDeTema compacto />
                    </div>
                    <AppDropdownSeparador />
                    <AppDropdownItem rotulo="Perfil e conta" :icone="UserCircle" as="Link" :href="route('profile.edit')" />
                    <AppDropdownItem rotulo="Refazer o passo a passo" :icone="RotateCcw" @click="tour.refazer()" />
                    <AppDropdownSeparador />
                    <AppDropdownItem rotulo="Sair" :icone="LogOut" as="Link" :href="route('logout')" method="post" />
                </AppDropdown>
            </div>
        </aside>

        <!-- Topo mobile -->
        <header class="fixed inset-x-0 top-0 z-40 flex h-14 items-center justify-between border-b border-line bg-bg-1 px-3 md:hidden">
            <Link :href="route('dashboard')" class="font-display text-lg font-bold tracking-tight text-fg-0">{{ nomeDoProduto }}<span class="text-primary">.</span></Link>
            <IconButton ref="burger" :icone="Menu" rotulo="Abrir menu" :aria-expanded="gavetaAberta" @click="abrirGaveta" />
        </header>

        <dialog
            v-if="gavetaAberta"
            ref="gaveta"
            aria-label="Menu"
            class="m-0 h-dvh max-h-none w-[280px] max-w-[85vw] bg-bg-0 p-0 text-fg-0 shadow-3 backdrop:bg-fg-0/40"
            @keydown.escape.prevent="fecharGaveta"
            @cancel.prevent="fecharGaveta"
            @click="(e) => e.target === gaveta && fecharGaveta()"
        >
            <div class="flex h-full flex-col">
                <div class="flex h-14 items-center justify-between pl-5 pr-2">
                    <span class="font-display text-lg font-bold tracking-tight">{{ nomeDoProduto }}<span class="text-primary">.</span></span>
                    <IconButton :icone="X" rotulo="Fechar menu" tamanho="sm" @click="fecharGaveta" />
                </div>
                <nav aria-label="Principal" class="flex flex-1 flex-col gap-1 overflow-y-auto px-2.5 py-2">
                    <NavItens :grupos="grupos" @navegar="fecharGaveta" />
                    <p class="px-3 pb-1.5 pt-5 text-sm font-medium text-fg-2">Conta</p>
                    <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded px-3 py-2.5 text-md text-fg-1 hover:bg-bg-1/70 hover:text-fg-0" @click="fecharGaveta">
                        <UserCircle :size="18" aria-hidden="true" /> Perfil e conta
                    </Link>
                    <button type="button" class="flex w-full items-center gap-3 rounded px-3 py-2.5 text-left text-md text-fg-1 hover:bg-bg-1/70 hover:text-fg-0" @click="fecharGaveta(); tour.refazer()">
                        <RotateCcw :size="18" aria-hidden="true" /> Refazer o passo a passo
                    </button>
                    <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 rounded px-3 py-2.5 text-left text-md text-fg-1 hover:bg-bg-1/70 hover:text-fg-0">
                        <LogOut :size="18" aria-hidden="true" /> Sair
                    </Link>
                    <p class="px-3 pb-1.5 pt-5 text-sm font-medium text-fg-2">Tema</p>
                    <div class="px-3"><SeletorDeTema /></div>
                </nav>
                <div class="m-2.5 flex items-center gap-2.5 rounded-lg bg-bg-1 p-2.5 shadow-2">
                    <AppAvatar :nome="user.name" :tamanho="32" />
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                        <span class="block truncate text-xs text-fg-2">{{ user.email }}</span>
                    </span>
                </div>
            </div>
        </dialog>

        <main class="min-w-0 flex-1 pt-14 transition-[margin] duration-3 ease-ui md:py-3 md:pr-3" :class="colapsada ? 'md:ml-14' : 'md:ml-[232px]'">
            <!-- Folha: o conteúdo flutua sobre o fundo, ao lado da barra lateral -->
            <div data-folha class="flex min-h-[calc(100dvh-3.5rem)] flex-col bg-bg-1 md:min-h-[calc(100dvh-1.5rem)] md:rounded-xl md:shadow-2">
                <!-- Faixa da assinatura: teste acabando, teste encerrado, plano vencido/cancelado -->
                <div v-if="faixaAssinatura" class="border-b px-4 py-2 text-sm md:rounded-t-xl md:px-6" :class="faixaAssinatura.tom === 'danger' ? 'border-danger-line bg-danger-subtle text-danger' : 'border-warn-line bg-warn-subtle text-warn'" role="status">
                    <span>{{ faixaAssinatura.texto }}</span>
                    <Link v-if="pode('configuracoes.manage')" :href="route('configuracoes.assinatura')" class="ml-2 font-semibold underline underline-offset-2">{{ faixaAssinatura.acao }}</Link>
                    <span v-else class="ml-2 text-fg-1">Peça ao administrador da empresa.</span>
                </div>
                <div class="min-w-0 flex-1"><slot /></div>
            </div>
        </main>

        <ToastHost />
        <ConfirmHost />
        <PerdaHost />
        <BoasVindasModal />
        <TourHost />
    </div>
</template>
