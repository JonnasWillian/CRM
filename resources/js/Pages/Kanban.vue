<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import draggable from 'vuedraggable';
import { ArrowRightLeft, Calendar, ChevronDown, Clock, ExternalLink, GitBranch, MessageCircle, Plus, Settings2, SquareKanban, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppDropdownSeparador from '@/Components/ui/AppDropdownSeparador.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppSkeleton from '@/Components/ui/AppSkeleton.vue';
import AppSwitch from '@/Components/ui/AppSwitch.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ConfirmarGanhoDialog from '@/Components/leads/ConfirmarGanhoDialog.vue';
import LeadFormModal from '@/Components/leads/LeadFormModal.vue';
import MoverFunilDialog from '@/Components/leads/MoverFunilDialog.vue';
import TransferirDialog from '@/Components/leads/TransferirDialog.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { usePerda } from '@/Composables/usePerda';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatBRL, formatarData, formatarRelativo } from '@/utils/formatadores';
import { ehTelefoneBrasileiro, MASCARA_TELEFONE } from '@/utils/telefone';

/**
 * Pipeline: o quadro vem agrupado do servidor (GET api/kanban) — total, Σ
 * valor e os primeiros cards por coluna; "carregar mais" pagina uma coluna.
 * Arrastar move (com Desfazer); soltar em perdido pede motivo; em ganho,
 * confirma o valor. Filtros na URL (funil_id, q, user_id, estagnados).
 *
 * SortableJS em modo fallback (eventos de ponteiro, não o DnD nativo do
 * HTML5): mesmo comportamento no toque e no mouse, fantasma consistente e
 * autoscroll do quadro; no toque, 150 ms de espera para a rolagem vertical
 * da coluna continuar funcionando.
 */
const props = defineProps({
    funis: { type: Array, required: true },
    funilAtual: { type: Object, default: null },
    estagios: { type: Array, required: true }, // todos os estágios do tenant
    agentes: { type: Array, default: () => [] },
});

const toast = useToast();
const { confirmar } = useConfirm();
const { pedirPerda } = usePerda();
const pode = (p) => usePage().props.auth.permissions?.[p] === true;
const eu = computed(() => usePage().props.auth.user);

// ── Filtros (URL) ──
const inicial = new URLSearchParams(window.location.search);
const filtros = reactive({
    funil_id: Number(inicial.get('funil_id')) || props.funilAtual?.id || null,
    q: inicial.get('q') ?? '',
    user_id: Number(inicial.get('user_id')) || null,
    estagnados: inicial.get('estagnados') === '1',
});
const query = () => ({
    ...(filtros.funil_id ? { funil_id: filtros.funil_id } : {}),
    ...(filtros.q ? { q: filtros.q } : {}),
    ...(filtros.user_id ? { user_id: filtros.user_id } : {}),
    ...(filtros.estagnados ? { estagnados: 1 } : {}),
});
function sincronizarUrl() {
    const url = new URL(window.location.href);
    url.search = new URLSearchParams(query()).toString();
    window.history.replaceState(window.history.state, '', url);
}

// ── Quadro ──
const quadro = ref(null);
const carregando = ref(false);
const erro = ref(null);
let controlador = null;

async function carregar() {
    controlador?.abort();
    controlador = new AbortController();
    carregando.value = true;
    erro.value = null;
    sincronizarUrl();
    try {
        const { data } = await axios.get(route('api.kanban.index'), { params: query(), signal: controlador.signal, silencioso: true });
        quadro.value = data;
    } catch (e) {
        if (axios.isCancel(e)) return;
        erro.value = mensagemDeErro(e, 'Não foi possível carregar o quadro.');
    } finally {
        carregando.value = false;
    }
}
onMounted(carregar);

const colunas = computed(() => quadro.value?.estagios ?? []);
const funil = computed(() => quadro.value?.funil ?? props.funilAtual);
const totais = computed(() => quadro.value?.totais ?? null);
const opcoesFunil = computed(() => props.funis.map((f) => ({ valor: f.id, rotulo: f.nome })));
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.name })));
const funisComEstagios = computed(() => props.funis.map((f) => ({ ...f, estagios: props.estagios.filter((e) => e.funil_id === f.id) })));
const filtrosAtivos = computed(() => Boolean(filtros.q || filtros.user_id || filtros.estagnados));

let temporizador = null;
function buscar(valor) {
    filtros.q = valor;
    clearTimeout(temporizador);
    temporizador = setTimeout(carregar, 300);
}
function limparFiltros() {
    filtros.q = ''; filtros.user_id = null; filtros.estagnados = false;
    carregar();
}

// ── Cards ──
const whatsapp = (l) => { const d = String(l.telefone ?? '').replace(/\D/g, ''); return d.length >= 10 ? `https://wa.me/${d}` : null; };
const previsaoVencida = (l, coluna) => l.previsao_fechamento && coluna.tipo === 'aberto' && new Date(l.previsao_fechamento) < new Date(new Date().toDateString());
const hrefDoLead = (l) => route('leads.show', { usuario: l.id, de: 'pipeline' });
const corDaColuna = (c) => (c.tipo === 'ganho' ? 'var(--ok)' : c.tipo === 'perdido' ? 'var(--danger)' : c.cor || 'var(--cat-neutral)');

async function carregarMais(coluna) {
    if (!coluna.proxima_pagina || coluna.carregandoMais) return;
    coluna.carregandoMais = true;
    try {
        const { data } = await axios.get(route('api.kanban.coluna', coluna.id), { params: { ...query(), page: coluna.proxima_pagina } });
        const ids = new Set(coluna.leads.map((l) => l.id));
        coluna.leads.push(...data.data.filter((l) => !ids.has(l.id)));
        coluna.proxima_pagina = data.meta.current_page < data.meta.last_page ? data.meta.current_page + 1 : null;
    } catch { /* interceptor */ } finally {
        coluna.carregandoMais = false;
    }
}

// ── Transições ──
const patchEstagio = (lead, payload, opcoes = {}) => axios.patch(route('api.leads.estagio', lead.id), payload, opcoes);
const ganho = ref({ aberto: false, lead: null, destino: null, salvando: false, erro: '' });

/** Depois de o card já ter mudado de lista na tela (arraste ou teclado). Cancelar recarrega o quadro (rollback). */
async function moverPara(lead, destino, origemId) {
    if (destino.arquivada || destino.id === origemId) return carregar();
    lead.estagio_id = destino.id;

    if (destino.tipo === 'perdido') {
        const ok = await pedirPerda({
            titulo: `Perder “${lead.nome}”`,
            detalhe: lead.projetos_abertos_count > 0 ? `Este lead tem ${lead.projetos_abertos_count} projeto(s) em aberto. Eles continuam como estão; feche-os no perfil se for o caso.` : null,
            enviar: (perda) => patchEstagio(lead, { estagio_id: destino.id, perda }),
        });
        if (ok) toast.sucesso(`Perda registrada para “${lead.nome}”.`);
        return carregar();
    }

    if (destino.tipo === 'ganho') {
        ganho.value = { aberto: true, lead, destino, salvando: false, erro: '' };
        return;
    }

    try {
        await patchEstagio(lead, { estagio_id: destino.id });
        toast.sucesso(`“${lead.nome}” movido para “${destino.descricao}”.`, {
            acao: { rotulo: 'Desfazer', onClick: () => patchEstagio(lead, { estagio_id: origemId }).then(carregar) },
        });
    } catch { /* interceptor */ }
    carregar();
}

async function confirmarGanho({ valor_fechado }) {
    const { lead, destino } = ganho.value;
    ganho.value.salvando = true;
    ganho.value.erro = '';
    try {
        await patchEstagio(lead, { estagio_id: destino.id, valor_fechado }, { silencioso: true });
        ganho.value.aberto = false;
        toast.sucesso(`Negócio ganho: “${lead.nome}”.`);
        carregar();
    } catch (e) {
        ganho.value.erro = mensagemDeErro(e, 'Não foi possível marcar como ganho.');
    } finally {
        ganho.value.salvando = false;
    }
}
function cancelarGanho() {
    ganho.value.aberto = false;
    carregar();
}

// vuedraggable: `added` dispara na coluna de destino com o card já lá.
function aoMudar(evento, coluna) {
    if (evento.added) moverPara(evento.added.element, coluna, evento.added.element.estagio_id);
}

function moverPeloMenu(lead, colunaAtual, destino) {
    const origem = colunas.value.find((c) => c.id === colunaAtual.id);
    const i = origem.leads.findIndex((l) => l.id === lead.id);
    if (i >= 0) origem.leads.splice(i, 1);
    destino.leads.unshift(lead);
    moverPara(lead, destino, colunaAtual.id);
}

// Shift+←/→ move para a coluna vizinha (pulando arquivadas); M abre o menu do card.
function aoTeclarCard(e, lead, coluna) {
    if (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(e.target.tagName)) return;
    if (e.shiftKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
        e.preventDefault();
        const i = colunas.value.findIndex((c) => c.id === coluna.id);
        const passo = e.key === 'ArrowRight' ? 1 : -1;
        let j = i + passo;
        while (colunas.value[j]?.arquivada) j += passo;
        const destino = colunas.value[j];
        if (destino) moverPeloMenu(lead, coluna, destino);
    } else if (e.key === 'm' || e.key === 'M') {
        e.preventDefault();
        e.currentTarget.querySelector('[data-menu-card]')?.click();
    }
}

// ── Mover de funil ──
const funilDialogo = ref({ aberto: false, lead: null, salvando: false, erro: '' });
async function confirmarFunil({ funil_id, estagio_id }) {
    const lead = funilDialogo.value.lead;
    const destinoFunil = funisComEstagios.value.find((f) => f.id === funil_id);
    const destino = destinoFunil?.estagios.find((e) => e.id === estagio_id);
    const enviar = (perda) => axios.patch(route('api.leads.funil', lead.id), { funil_id, estagio_id, perda }, { silencioso: true });

    if (destino?.tipo === 'perdido') {
        funilDialogo.value.aberto = false;
        const ok = await pedirPerda({ titulo: `Perder “${lead.nome}”`, enviar });
        if (ok) { toast.sucesso(`“${lead.nome}” movido para “${destinoFunil.nome}” e registrado como perda.`); carregar(); }
        return;
    }
    funilDialogo.value.salvando = true;
    funilDialogo.value.erro = '';
    try {
        await enviar(null);
        funilDialogo.value.aberto = false;
        toast.sucesso(`“${lead.nome}” movido para “${destinoFunil?.nome}” › “${destino?.descricao}”.`);
        carregar();
    } catch (e) {
        funilDialogo.value.erro = mensagemDeErro(e, 'Não foi possível mover de funil.');
    } finally {
        funilDialogo.value.salvando = false;
    }
}

// ── Transferir (leads.view-all) ──
const transferencia = ref({ aberto: false, lead: null, salvando: false, erro: '' });
async function transferir({ user_id }) {
    const lead = transferencia.value.lead;
    const anterior = lead.responsavel?.id ?? null;
    transferencia.value.salvando = true;
    transferencia.value.erro = '';
    try {
        const { data } = await axios.patch(route('api.leads.responsavel', lead.id), { user_id }, { silencioso: true });
        transferencia.value.aberto = false;
        toast.sucesso(data.message, anterior ? { acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.leads.responsavel', lead.id), { user_id: anterior }).then(carregar) } } : undefined);
        carregar();
    } catch (e) {
        transferencia.value.erro = mensagemDeErro(e, 'Não foi possível transferir o lead.');
    } finally {
        transferencia.value.salvando = false;
    }
}

// ── Lixeira ──
async function excluir(lead) {
    const ok = await confirmar({
        titulo: `Mover “${lead.nome}” para a lixeira?`,
        mensagem: 'O lead some das listas e do pipeline, com projetos, tarefas, anotações e documentos. Você pode restaurá-lo na Lixeira.',
        rotuloConfirmar: 'Mover para a lixeira',
        tom: 'danger',
    });
    if (!ok) return;
    try {
        await axios.delete(route('api.leads.destroy', lead.id));
        toast.sucesso(`“${lead.nome}” movido para a lixeira.`, {
            acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.leads.restaurar', lead.id)).then(() => { toast.sucesso(`“${lead.nome}” restaurado.`); carregar(); }) },
        });
        carregar();
    } catch { /* interceptor */ }
}

// ── Quick-add ──
const quickAdd = reactive({ estagioId: null, nome: '', email: '', telefone: '', erros: {}, salvando: false });
function abrirQuickAdd(coluna) {
    Object.assign(quickAdd, { estagioId: coluna.id, nome: '', email: '', telefone: '', erros: {}, salvando: false });
}
const fecharQuickAdd = () => { quickAdd.estagioId = null; };
async function salvarQuickAdd(coluna) {
    if (!quickAdd.nome.trim() || !quickAdd.email.trim()) return;
    const payload = { nome: quickAdd.nome.trim(), email: quickAdd.email.trim(), telefone: quickAdd.telefone.trim() || null, estagio_id: coluna.id, funil_id: funil.value?.id ?? null };
    const enviar = (perda) => axios.post(route('api.leads.store'), { ...payload, perda }, { silencioso: true });

    if (coluna.tipo === 'perdido') {
        const ok = await pedirPerda({ titulo: `Cadastrar “${payload.nome}” já como perdido`, enviar });
        if (ok) { fecharQuickAdd(); toast.sucesso('Lead cadastrado.'); carregar(); }
        return;
    }
    quickAdd.salvando = true;
    quickAdd.erros = {};
    try {
        await enviar(null);
        fecharQuickAdd();
        toast.sucesso('Lead cadastrado.');
        carregar();
    } catch (e) {
        quickAdd.erros = errosDeValidacao(e);
        if (!Object.keys(quickAdd.erros).length) toast.erro(mensagemDeErro(e, 'Não foi possível cadastrar o lead.'));
    } finally {
        quickAdd.salvando = false;
    }
}

// ── Coluna padrão para novos leads ──
const colunaPadraoId = ref(eu.value?.kanban_default_estagio_id ?? null);
const configuracao = ref({ aberto: false, escolhido: null, salvando: false });
const opcoesColunaPadrao = computed(() => colunas.value.filter((c) => !c.arquivada).map((c) => ({ valor: c.id, rotulo: c.descricao })));
function abrirConfiguracao() {
    configuracao.value = { aberto: true, escolhido: colunas.value.some((c) => c.id === colunaPadraoId.value) ? colunaPadraoId.value : null, salvando: false };
}
async function salvarConfiguracao() {
    configuracao.value.salvando = true;
    try {
        await axios.patch(route('api.kanban.settings'), { default_estagio_id: configuracao.value.escolhido });
        colunaPadraoId.value = configuracao.value.escolhido;
        configuracao.value.aberto = false;
        toast.sucesso('Coluna padrão atualizada.');
    } catch { /* interceptor */ } finally {
        configuracao.value.salvando = false;
    }
}

// ── Novo lead (modal completo) ──
const modal = ref(false);

// Atalhos: N novo lead, / busca
function atalho(e) {
    const alvo = e.target;
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo?.tagName) || alvo?.isContentEditable) return;
    if (e.key === '/') { e.preventDefault(); document.querySelector('input[type="search"]')?.focus(); }
    else if ((e.key === 'n' || e.key === 'N') && pode('leads.manage')) { e.preventDefault(); modal.value = true; }
}
onMounted(() => window.addEventListener('keydown', atalho));
onBeforeUnmount(() => { window.removeEventListener('keydown', atalho); clearTimeout(temporizador); controlador?.abort(); });
</script>

<template>
    <Head title="Pipeline" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Pipeline" :subtitulo="totais ? `${totais.leads} ${totais.leads === 1 ? 'lead' : 'leads'} · ${formatBRL(totais.valor_estimado_aberto)} em aberto · ${formatBRL(totais.valor_ponderado_aberto)} ponderado` : null">
                <template #acoes>
                    <AppDropdown rotulo="Mais ações" alinhar="end">
                        <AppDropdownItem rotulo="Coluna padrão para novos leads…" :icone="Settings2" @click="abrirConfiguracao" />
                        <AppDropdownItem v-if="pode('configuracoes.manage')" rotulo="Configurar funis" :icone="GitBranch" as="Link" :href="route('configuracoes.funis')" />
                    </AppDropdown>
                    <AppButton v-if="pode('leads.manage')" @click="modal = true"><template #icone><Plus /></template>Novo lead</AppButton>
                </template>
                <template #abaixo>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <AppSelect :model-value="filtros.funil_id" :opcoes="opcoesFunil" :disabled="funis.length <= 1" tamanho="sm" aria-label="Funil" class="w-44" @update:model-value="(v) => { filtros.funil_id = v; carregar(); }" />
                        <label class="relative">
                            <span class="sr-only">Buscar</span>
                            <input type="search" :value="filtros.q" placeholder="Buscar no quadro" class="form-input h-8 w-48 rounded border-line bg-bg-1 pl-3 pr-3 text-sm text-fg-0 placeholder:text-fg-disabled focus:border-focus focus:ring-2 focus:ring-focus/25" @input="buscar($event.target.value)" />
                        </label>
                        <AppSelect v-if="agentes.length" :model-value="filtros.user_id" :opcoes="opcoesAgente" placeholder="Todos os responsáveis" tamanho="sm" aria-label="Responsável" class="w-44" @update:model-value="(v) => { filtros.user_id = v; carregar(); }" />
                        <AppSwitch :model-value="filtros.estagnados" rotulo="Só parados +14d" @update:model-value="(v) => { filtros.estagnados = v; carregar(); }" />
                        <button v-if="filtrosAtivos" type="button" class="h-8 rounded px-2 text-sm font-medium text-primary hover:bg-primary-subtle" @click="limparFiltros">Limpar</button>
                    </div>
                </template>
            </PageHeader>

            <AppAlert v-if="erro" tom="danger" titulo="Não deu para carregar o quadro">
                {{ erro }}
                <template #acao><AppButton variante="secondary" tamanho="sm" @click="carregar">Tentar de novo</AppButton></template>
            </AppAlert>

            <!-- Esqueleto -->
            <div v-else-if="!quadro" class="flex gap-3 overflow-hidden" aria-busy="true">
                <div v-for="i in 4" :key="i" class="w-[272px] shrink-0 space-y-2 rounded-lg border border-line bg-bg-2/60 p-2">
                    <AppSkeleton largura="50%" />
                    <AppSkeleton v-for="j in 3" :key="j" variante="rect" altura="76px" />
                </div>
            </div>

            <EmptyState v-else-if="!funil" :icone="SquareKanban" titulo="Nenhum funil configurado" descricao="Crie um funil com estágios para começar a mover leads.">
                <template #acao><AppButton v-if="pode('configuracoes.manage')" as="Link" :href="route('configuracoes.funis')">Configurar funis</AppButton></template>
            </EmptyState>

            <EmptyState v-else-if="colunas.length === 0" :icone="SquareKanban" titulo="Este funil não tem estágios" descricao="Configure os estágios para começar a mover leads.">
                <template #acao><AppButton v-if="pode('configuracoes.manage')" as="Link" :href="route('configuracoes.funis')">Configurar funis</AppButton></template>
            </EmptyState>

            <!-- Quadro -->
            <div v-else class="kb-quadro -mx-[var(--page-x)] flex snap-x snap-mandatory gap-3 overflow-x-auto px-[var(--page-x)] pb-3 md:mx-0 md:snap-none md:px-0" :class="carregando ? 'opacity-70' : ''" :aria-busy="carregando ? 'true' : undefined">
                <section
                    v-for="coluna in colunas"
                    :key="coluna.id"
                    class="flex w-[min(272px,calc(100vw-2*var(--page-x)))] shrink-0 snap-start flex-col rounded-lg border border-line bg-bg-2/60 md:w-[272px]"
                    :aria-label="coluna.descricao"
                >
                    <header class="border-b border-line px-3 py-2" :style="{ borderTop: `3px solid ${corDaColuna(coluna)}`, borderTopLeftRadius: 'var(--r-3)', borderTopRightRadius: 'var(--r-3)' }">
                        <div class="flex items-center gap-2">
                            <h2 class="min-w-0 flex-1 truncate text-sm font-semibold text-fg-0">{{ coluna.descricao }}</h2>
                            <AppBadge v-if="coluna.id === colunaPadraoId" tom="primary">Padrão</AppBadge>
                            <AppBadge v-if="coluna.arquivada" tom="warn">Arquivado</AppBadge>
                            <span class="num rounded-full bg-bg-1 px-2 py-0.5 text-xs font-medium text-fg-1">{{ coluna.total }}</span>
                        </div>
                        <p v-if="coluna.tipo === 'aberto' && (coluna.valor_estimado || coluna.valor_projetos)" class="num mt-1 truncate text-xs text-fg-2">
                            {{ formatBRL(coluna.valor_estimado) }}<span v-if="coluna.valor_ponderado"> · {{ formatBRL(coluna.valor_ponderado) }} pond.</span><span v-if="coluna.sem_probabilidade" class="text-warn"> · faltam {{ coluna.sem_probabilidade }} prob.</span>
                        </p>
                    </header>

                    <draggable
                        :list="coluna.leads"
                        :group="{ name: 'leads', put: !coluna.arquivada }"
                        item-key="id"
                        :disabled="!pode('leads.manage')"
                        ghost-class="kb-fantasma"
                        drag-class="kb-arrastando"
                        filter=".kb-nao-arrastar"
                        :prevent-on-filter="false"
                        :animation="150"
                        :force-fallback="true"
                        :fallback-tolerance="4"
                        :delay="150"
                        :delay-on-touch-only="true"
                        :scroll-sensitivity="80"
                        class="kb-lista flex min-h-[80px] flex-1 flex-col gap-2 overflow-y-auto p-2"
                        @change="aoMudar($event, coluna)"
                    >
                        <template #item="{ element: lead }">
                            <article
                                tabindex="0"
                                class="rounded-md border border-line bg-bg-1 p-2.5 shadow-1 transition-shadow duration-1 ease-ui hover:shadow-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-focus/40"
                                :class="pode('leads.manage') && !coluna.arquivada ? 'cursor-grab active:cursor-grabbing' : ''"
                                :aria-label="`${lead.nome}. Shift+setas move de coluna, M abre o menu.`"
                                @keydown="aoTeclarCard($event, lead, coluna)"
                            >
                                <div class="flex items-start gap-2">
                                    <AppAvatar :nome="lead.nome" :tamanho="28" />
                                    <div class="min-w-0 flex-1">
                                        <Link :href="hrefDoLead(lead)" class="block truncate text-md font-medium text-fg-0 hover:text-primary">{{ lead.nome }}</Link>
                                        <p class="truncate text-xs text-fg-2">{{ lead.responsavel?.name && agentes.length ? lead.responsavel.name : lead.email }}</p>
                                    </div>
                                    <div class="kb-nao-arrastar -mr-1 -mt-1">
                                        <AppDropdown rotulo="Ações do lead" alinhar="end" tamanho="sm">
                                            <template #gatilho="{ alternar, atributos }">
                                                <button type="button" data-menu-card v-bind="atributos" aria-label="Ações do lead" class="inline-flex h-8 w-8 items-center justify-center rounded text-fg-2 hover:bg-bg-2 hover:text-fg-0" @click.stop="alternar">
                                                    <ChevronDown :size="16" aria-hidden="true" />
                                                </button>
                                            </template>
                                            <AppDropdownItem rotulo="Abrir" :icone="ExternalLink" as="Link" :href="hrefDoLead(lead)" />
                                            <AppDropdownItem v-if="whatsapp(lead)" rotulo="Abrir no WhatsApp" :icone="MessageCircle" as="a" :href="whatsapp(lead)" />
                                            <template v-if="pode('leads.manage')">
                                                <AppDropdownSeparador />
                                                <p class="px-2.5 pb-1 pt-1.5 text-xs font-medium uppercase tracking-wider text-fg-2">Mover para</p>
                                                <AppDropdownItem v-for="c in colunas.filter((x) => !x.arquivada && x.id !== coluna.id)" :key="c.id" :rotulo="c.descricao" :tom="c.tipo === 'perdido' ? 'danger' : 'default'" @click="moverPeloMenu(lead, coluna, c)" />
                                                <AppDropdownItem v-if="funis.length > 1" rotulo="Mover para outro funil…" :icone="ArrowRightLeft" @click="funilDialogo = { aberto: true, lead, salvando: false, erro: '' }" />
                                                <AppDropdownItem v-if="agentes.length" rotulo="Transferir…" :icone="ArrowRightLeft" @click="transferencia = { aberto: true, lead, salvando: false, erro: '' }" />
                                                <AppDropdownSeparador />
                                                <AppDropdownItem rotulo="Mover para a lixeira" :icone="Trash2" tom="danger" @click="excluir(lead)" />
                                            </template>
                                        </AppDropdown>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-fg-2">
                                    <span v-if="lead.valor_estimado" class="num font-medium text-fg-0">{{ formatBRL(lead.valor_estimado) }}</span>
                                    <AppBadge v-if="lead.probabilidade !== null && lead.probabilidade !== undefined && coluna.tipo === 'aberto'" tom="info">{{ lead.probabilidade }}%</AppBadge>
                                    <span class="inline-flex items-center gap-1" :class="lead.estagnado ? 'font-medium text-warn' : ''" :title="lead.estagnado ? 'Sem contato há mais de 14 dias' : 'Último contato'">
                                        <Clock :size="12" aria-hidden="true" />{{ formatarRelativo(lead.ultimo_contato ?? lead.created_at) ?? '—' }}
                                    </span>
                                    <span v-if="lead.previsao_fechamento" class="inline-flex items-center gap-1" :class="previsaoVencida(lead, coluna) ? 'font-medium text-danger' : ''">
                                        <Calendar :size="12" aria-hidden="true" />{{ formatarData(lead.previsao_fechamento) }}
                                    </span>
                                    <span v-if="lead.valor_projetos" class="num">{{ formatBRL(lead.valor_projetos) }} em projetos</span>
                                </div>
                            </article>
                        </template>
                        <template #footer>
                            <p v-if="coluna.leads.length === 0 && quickAdd.estagioId !== coluna.id" class="rounded-md border border-dashed border-line px-3 py-5 text-center text-xs text-fg-2">
                                {{ filtrosAtivos ? 'Nenhum lead com esses filtros' : 'Nenhum lead aqui' }}<br /><span v-if="!filtrosAtivos && !coluna.arquivada">Arraste um lead ou clique em Adicionar.</span>
                            </p>
                            <button v-if="coluna.proxima_pagina" type="button" class="kb-nao-arrastar w-full rounded-md border border-line bg-bg-1 py-2 text-sm font-medium text-primary hover:bg-primary-subtle" :disabled="coluna.carregandoMais" @click="carregarMais(coluna)">
                                {{ coluna.carregandoMais ? 'Carregando…' : `Carregar mais (${coluna.total - coluna.leads.length})` }}
                            </button>
                        </template>
                    </draggable>

                    <footer v-if="pode('leads.manage') && !coluna.arquivada" class="kb-nao-arrastar border-t border-line p-2">
                        <form v-if="quickAdd.estagioId === coluna.id" class="space-y-2" @submit.prevent="salvarQuickAdd(coluna)">
                            <FormField rotulo="Nome" obrigatorio :erro="quickAdd.erros.nome"><AppInput v-model="quickAdd.nome" tamanho="sm" limite="nome" autofocus /></FormField>
                            <FormField rotulo="E-mail" obrigatorio :erro="quickAdd.erros.email"><AppInput v-model="quickAdd.email" type="email" tamanho="sm" limite="email" /></FormField>
                            <FormField rotulo="Telefone" :erro="quickAdd.erros.telefone"><AppInput v-model="quickAdd.telefone" tamanho="sm" inputmode="tel" :mascara="ehTelefoneBrasileiro(quickAdd.telefone) ? MASCARA_TELEFONE : null" /></FormField>
                            <div class="flex justify-end gap-2">
                                <AppButton variante="ghost" tamanho="sm" @click="fecharQuickAdd">Cancelar</AppButton>
                                <AppButton type="submit" tamanho="sm" :carregando="quickAdd.salvando" :disabled="!quickAdd.nome.trim() || !quickAdd.email.trim()">Adicionar</AppButton>
                            </div>
                        </form>
                        <button v-else type="button" class="flex h-9 w-full items-center justify-center gap-1.5 rounded-md text-sm font-medium text-fg-1 hover:bg-bg-1 hover:text-fg-0" @click="abrirQuickAdd(coluna)">
                            <Plus :size="16" aria-hidden="true" /> Adicionar
                        </button>
                    </footer>
                </section>
            </div>
        </PageContainer>

        <ConfirmarGanhoDialog :aberto="ganho.aberto" :nome="ganho.lead?.nome ?? ''" :valor-estimado="ganho.lead?.valor_estimado ?? null" :projetos-abertos="ganho.lead?.projetos_abertos_count ?? 0" :salvando="ganho.salvando" :erro="ganho.erro" @confirmar="confirmarGanho" @cancelar="cancelarGanho" />
        <MoverFunilDialog :aberto="funilDialogo.aberto" :nome="funilDialogo.lead?.nome ?? ''" :funis="funisComEstagios" :funil-atual-id="funil?.id ?? null" :salvando="funilDialogo.salvando" :erro="funilDialogo.erro" @confirmar="confirmarFunil" @cancelar="funilDialogo.aberto = false" />
        <TransferirDialog :aberto="transferencia.aberto" :titulo="`Transferir “${transferencia.lead?.nome ?? ''}”`" :agentes="agentes" :atual-id="transferencia.lead?.responsavel?.id ?? null" :salvando="transferencia.salvando" :erro="transferencia.erro" @confirmar="transferir" @cancelar="transferencia.aberto = false" />
        <LeadFormModal :aberto="modal" :funis="funis" :estagios="estagios" :agentes="agentes" @fechar="modal = false" @salvo="carregar" />

        <AppModal :aberto="configuracao.aberto" titulo="Coluna padrão para novos leads" descricao="Leads cadastrados sem estágio escolhido entram nesta coluna." tamanho="sm" @fechar="configuracao.aberto = false">
            <FormField rotulo="Coluna">
                <AppSelect v-model="configuracao.escolhido" :opcoes="opcoesColunaPadrao" placeholder="Primeiro estágio aberto" />
            </FormField>
            <template #rodape>
                <AppButton variante="secondary" @click="configuracao.aberto = false">Cancelar</AppButton>
                <AppButton :carregando="configuracao.salvando" @click="salvarConfiguracao">Salvar</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Altura do quadro: a página inteira menos cabeçalho e filtros; as colunas rolam por dentro. */
.kb-quadro { min-height: 320px; }
@media (min-width: 768px) {
    .kb-quadro > section { max-height: calc(100dvh - 230px); }
}
.kb-fantasma { opacity: 0.4; }
.kb-arrastando { transform: rotate(1.5deg); box-shadow: var(--sh-3); }
</style>
