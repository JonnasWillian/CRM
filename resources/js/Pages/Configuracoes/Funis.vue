<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import draggable from 'vuedraggable';
import { Archive, ArchiveRestore, ArrowDown, ArrowUp, GitBranch, GripVertical, Pencil, Plus, Star } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EstagioFormModal from '@/Components/configuracoes/EstagioFormModal.vue';
import FunilFormModal from '@/Components/configuracoes/FunilFormModal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

/**
 * Funis e estágios. A lista vem por props; cada escrita vai à API e a
 * página recarrega só `funis`. Estágios reordenam por arraste (alça) ou
 * pelas setas (teclado); funis, pelas setas.
 */
const props = defineProps({ funis: { type: Array, required: true } });

const toast = useToast();
const { confirmar } = useConfirm();

// Cópia local: o arraste precisa de listas mutáveis; as props são a verdade depois de cada recarga.
const lista = ref(estruturar(props.funis));
watch(() => props.funis, (v) => { lista.value = estruturar(v); });
function estruturar(funis) { return funis.map((f) => ({ ...f, estagios: [...f.estagios] })); }

const ativos = computed(() => lista.value.filter((f) => !f.arquivado));
const arquivados = computed(() => lista.value.filter((f) => f.arquivado));
const recarregar = () => router.reload({ only: ['funis'] });

const TIPO = { aberto: { rotulo: 'Aberto', tom: 'neutral' }, ganho: { rotulo: 'Ganho', tom: 'ok' }, perdido: { rotulo: 'Perdido', tom: 'danger' } };
const corDe = (e) => (e.tipo === 'ganho' ? 'var(--ok)' : e.tipo === 'perdido' ? 'var(--danger)' : e.cor || 'var(--cat-neutral)');

// ── Funis ──
const modalFunil = ref({ aberto: false, funil: null });
async function tornarPadrao(f) {
    const ok = await confirmar({ titulo: `Tornar “${f.nome}” o funil padrão?`, mensagem: 'Leads novos sem funil escolhido entram nele.', rotuloConfirmar: 'Tornar padrão', tom: 'primary' });
    if (!ok) return;
    try { await axios.patch(route('api.funis.padrao', f.id)); toast.sucesso(`“${f.nome}” agora é o padrão.`); recarregar(); } catch { /* interceptor */ }
}
async function arquivarFunil(f) {
    const ok = await confirmar({
        titulo: `Arquivar o funil “${f.nome}”?`,
        mensagem: f.total_leads > 0 ? `Ele ainda tem ${f.total_leads} lead(s): mova-os para outro funil antes.` : 'Os estágios dele são arquivados junto. Você pode restaurá-lo depois.',
        rotuloConfirmar: 'Arquivar',
        tom: 'danger',
    });
    if (!ok) return;
    try { await axios.delete(route('api.funis.destroy', f.id)); toast.sucesso('Funil arquivado.'); recarregar(); } catch { /* interceptor */ }
}
async function restaurarFunil(f) {
    try { await axios.patch(route('api.funis.restaurar', f.id)); toast.sucesso(`“${f.nome}” restaurado.`); recarregar(); } catch { /* interceptor */ }
}
async function moverFunil(f, passo) {
    const ids = ativos.value.map((x) => x.id);
    const i = ids.indexOf(f.id); const j = i + passo;
    if (j < 0 || j >= ids.length) return;
    [ids[i], ids[j]] = [ids[j], ids[i]];
    try { await axios.patch(route('api.funis.reordenar'), { ids }); toast.sucesso('Ordem salva.'); recarregar(); } catch { /* interceptor */ }
}

// ── Estágios ──
const modalEstagio = ref({ aberto: false, funilId: null, estagio: null });
const ativosDe = (f) => f.estagios.filter((e) => !e.arquivada);
const arquivadosDe = (f) => f.estagios.filter((e) => e.arquivada);

async function salvarOrdem(f) {
    try { await axios.patch(route('api.funis.estagios.reordenar', f.id), { ids: ativosDe(f).map((e) => e.id) }); toast.sucesso('Ordem salva.'); recarregar(); } catch { recarregar(); }
}
function moverEstagio(f, e, passo) {
    const abertos = ativosDe(f);
    const i = abertos.indexOf(e); const j = i + passo;
    if (j < 0 || j >= abertos.length) return;
    const a = f.estagios.indexOf(abertos[i]); const b = f.estagios.indexOf(abertos[j]);
    [f.estagios[a], f.estagios[b]] = [f.estagios[b], f.estagios[a]];
    salvarOrdem(f);
}
async function arquivarEstagio(f, e) {
    const ok = await confirmar({
        titulo: `Arquivar o estágio “${e.descricao}”?`,
        mensagem: 'Leads que estiverem nele continuam visíveis no Pipeline até serem movidos; o estágio não recebe leads novos.',
        rotuloConfirmar: 'Arquivar',
        tom: 'danger',
    });
    if (!ok) return;
    try { await axios.delete(route('api.estagios.destroy', e.id)); toast.sucesso('Estágio arquivado.'); recarregar(); } catch { /* interceptor */ }
}
async function restaurarEstagio(e) {
    try { await axios.patch(route('api.estagios.restaurar', e.id)); toast.sucesso(`“${e.descricao}” restaurado.`); recarregar(); } catch { /* interceptor */ }
}
</script>

<template>
    <Head title="Funis" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Funis" subtitulo="Cada funil tem seus estágios; o Pipeline mostra um funil por vez.">
                <template #acoes>
                    <AppButton @click="modalFunil = { aberto: true, funil: null }"><template #icone><Plus /></template>Novo funil</AppButton>
                </template>
            </PageHeader>

            <EmptyState v-if="ativos.length === 0" :icone="GitBranch" :titulo="arquivados.length ? 'Todos os funis estão arquivados' : 'Nenhum funil ainda'" descricao="Restaure um ou crie outro para o Pipeline funcionar.">
                <template #acao><AppButton @click="modalFunil = { aberto: true, funil: null }"><template #icone><Plus /></template>Novo funil</AppButton></template>
            </EmptyState>

            <div class="space-y-4">
                <AppCard v-for="(f, idx) in ativos" :key="f.id" padding="none">
                    <header class="flex flex-wrap items-start gap-3 border-b border-line px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-lg font-semibold text-fg-0">{{ f.nome }}</h2>
                                <AppBadge v-if="f.is_default" tom="primary">Padrão</AppBadge>
                                <span class="num text-sm text-fg-2">{{ f.total_leads }} {{ f.total_leads === 1 ? 'lead' : 'leads' }}</span>
                            </div>
                            <p v-if="f.descricao" class="mt-0.5 text-sm text-fg-2">{{ f.descricao }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <IconButton :icone="ArrowUp" rotulo="Mover funil para cima" tamanho="sm" :disabled="idx === 0" @click="moverFunil(f, -1)" />
                            <IconButton :icone="ArrowDown" rotulo="Mover funil para baixo" tamanho="sm" :disabled="idx === ativos.length - 1" @click="moverFunil(f, 1)" />
                            <AppButton v-if="!f.is_default" variante="ghost" tamanho="sm" @click="tornarPadrao(f)"><template #icone><Star /></template>Tornar padrão</AppButton>
                            <AppDropdown rotulo="Mais ações do funil" alinhar="end">
                                <AppDropdownItem rotulo="Editar" :icone="Pencil" @click="modalFunil = { aberto: true, funil: f }" />
                                <AppDropdownItem rotulo="Arquivar" :icone="Archive" tom="danger" @click="arquivarFunil(f)" />
                            </AppDropdown>
                        </div>
                    </header>

                    <draggable :list="f.estagios" item-key="id" handle=".alca" :animation="150" ghost-class="opacity-40" class="divide-y divide-line" @end="salvarOrdem(f)">
                        <template #item="{ element: e, index: i }">
                            <div v-if="!e.arquivada" class="flex items-center gap-2 px-3 py-2 hover:bg-bg-2/60">
                                <button type="button" class="alca inline-flex h-9 w-7 cursor-grab items-center justify-center rounded text-fg-disabled hover:text-fg-1 active:cursor-grabbing" aria-label="Arrastar para reordenar">
                                    <GripVertical :size="16" aria-hidden="true" />
                                </button>
                                <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: corDe(e) }" aria-hidden="true" />
                                <span class="min-w-0 flex-1 truncate text-md font-medium text-fg-0">{{ e.descricao }}</span>
                                <AppBadge :tom="TIPO[e.tipo]?.tom ?? 'neutral'">{{ TIPO[e.tipo]?.rotulo ?? e.tipo }}</AppBadge>
                                <span v-if="e.tipo === 'aberto' && e.probabilidade_padrao !== null" class="num hidden text-xs text-fg-2 sm:inline">{{ e.probabilidade_padrao }}%</span>
                                <div class="flex items-center">
                                    <IconButton :icone="ArrowUp" rotulo="Mover para cima" tamanho="sm" :disabled="ativosDe(f).indexOf(e) === 0" @click="moverEstagio(f, e, -1)" />
                                    <IconButton :icone="ArrowDown" rotulo="Mover para baixo" tamanho="sm" :disabled="ativosDe(f).indexOf(e) === ativosDe(f).length - 1" @click="moverEstagio(f, e, 1)" />
                                    <IconButton :icone="Pencil" rotulo="Editar estágio" tamanho="sm" @click="modalEstagio = { aberto: true, funilId: f.id, estagio: e }" />
                                    <IconButton :icone="Archive" rotulo="Arquivar estágio" tamanho="sm" variante="danger" @click="arquivarEstagio(f, e)" />
                                </div>
                            </div>
                        </template>
                        <template #footer>
                            <p v-if="ativosDe(f).length === 0" class="px-4 py-4 text-sm text-fg-2">Nenhum estágio ativo. Crie o primeiro para o Pipeline deste funil funcionar.</p>
                            <details v-if="arquivadosDe(f).length" class="border-t border-line px-3 py-2">
                                <summary class="cursor-pointer text-sm font-medium text-fg-1">Arquivados ({{ arquivadosDe(f).length }})</summary>
                                <ul class="mt-2 divide-y divide-line">
                                    <li v-for="e in arquivadosDe(f)" :key="e.id" class="flex items-center gap-2 py-2 text-fg-2">
                                        <span class="h-3 w-3 shrink-0 rounded-full opacity-50" :style="{ background: corDe(e) }" aria-hidden="true" />
                                        <span class="min-w-0 flex-1 truncate text-md">{{ e.descricao }}</span>
                                        <AppBadge tom="warn">Arquivado</AppBadge>
                                        <AppButton variante="ghost" tamanho="sm" @click="restaurarEstagio(e)"><template #icone><ArchiveRestore /></template>Restaurar</AppButton>
                                    </li>
                                </ul>
                            </details>
                            <div class="border-t border-line p-2">
                                <AppButton variante="ghost" tamanho="sm" @click="modalEstagio = { aberto: true, funilId: f.id, estagio: null }"><template #icone><Plus /></template>Novo estágio</AppButton>
                            </div>
                        </template>
                    </draggable>
                </AppCard>
            </div>

            <details v-if="arquivados.length" class="mt-6">
                <summary class="cursor-pointer text-sm font-medium text-fg-1">Funis arquivados ({{ arquivados.length }})</summary>
                <ul class="mt-2 divide-y divide-line rounded-lg border border-line bg-bg-1">
                    <li v-for="f in arquivados" :key="f.id" class="flex items-center gap-3 px-4 py-2.5">
                        <span class="min-w-0 flex-1 truncate text-md text-fg-1">{{ f.nome }} <span class="num text-fg-2">· {{ f.estagios.length }} estágio(s)</span></span>
                        <AppButton variante="secondary" tamanho="sm" @click="restaurarFunil(f)"><template #icone><ArchiveRestore /></template>Restaurar</AppButton>
                    </li>
                </ul>
            </details>
        </PageContainer>

        <FunilFormModal :aberto="modalFunil.aberto" :funil="modalFunil.funil" @fechar="modalFunil.aberto = false" @salvo="recarregar" />
        <EstagioFormModal :aberto="modalEstagio.aberto" :funil-id="modalEstagio.funilId ?? 0" :estagio="modalEstagio.estagio" @fechar="modalEstagio.aberto = false" @salvo="recarregar" />
    </AuthenticatedLayout>
</template>
