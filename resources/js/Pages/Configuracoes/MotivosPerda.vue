<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import draggable from 'vuedraggable';
import { Archive, ArchiveRestore, ArrowDown, ArrowUp, CircleX, GripVertical, Pencil, Plus } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';

/** Motivos de perda: a lista que o modal de perda oferece. Ordem por arraste ou setas. */
const props = defineProps({ motivos: { type: Array, required: true } });

const toast = useToast();
const { confirmar } = useConfirm();
const pode = (p) => usePage().props.auth.permissions?.[p] === true;

const lista = ref([...props.motivos]);
watch(() => props.motivos, (v) => { lista.value = [...v]; });
const ativos = computed(() => lista.value.filter((m) => !m.arquivado));
const arquivados = computed(() => lista.value.filter((m) => m.arquivado));
const recarregar = () => router.reload({ only: ['motivos'] });

const modal = reactive({ aberto: false, motivo: null, descricao: '', erros: {}, salvando: false });
function abrir(m = null) { Object.assign(modal, { aberto: true, motivo: m, descricao: m?.descricao ?? '', erros: {}, salvando: false }); }
async function salvar() {
    modal.salvando = true;
    modal.erros = {};
    try {
        if (modal.motivo) await axios.put(route('api.motivos-perda.update', modal.motivo.id), { descricao: modal.descricao }, { silencioso: true });
        else await axios.post(route('api.motivos-perda.store'), { descricao: modal.descricao }, { silencioso: true });
        toast.sucesso(modal.motivo ? 'Motivo salvo.' : 'Motivo criado.');
        modal.aberto = false;
        recarregar();
    } catch (e) {
        modal.erros = errosDeValidacao(e);
        if (!Object.keys(modal.erros).length) toast.erro(mensagemDeErro(e, 'Não foi possível salvar o motivo.'));
    } finally {
        modal.salvando = false;
    }
}

async function arquivar(m) {
    const ok = await confirmar({
        titulo: `Arquivar “${m.descricao}”?`,
        mensagem: m.total_perdas > 0 ? `Já usado em ${m.total_perdas} perda(s); o histórico e o relatório não mudam. Ele só deixa de aparecer para novas perdas.` : 'Ele deixa de aparecer para novas perdas. Você pode restaurá-lo depois.',
        rotuloConfirmar: 'Arquivar',
        tom: 'danger',
    });
    if (!ok) return;
    try { await axios.delete(route('api.motivos-perda.destroy', m.id)); toast.sucesso('Motivo arquivado.'); recarregar(); } catch { /* interceptor */ }
}
async function restaurar(m) {
    try { await axios.patch(route('api.motivos-perda.restaurar', m.id)); toast.sucesso(`“${m.descricao}” restaurado.`); recarregar(); } catch { /* interceptor */ }
}
async function salvarOrdem() {
    try { await axios.patch(route('api.motivos-perda.reordenar'), { ids: ativos.value.map((m) => m.id) }); toast.sucesso('Ordem salva.'); recarregar(); } catch { recarregar(); }
}
function mover(m, passo) {
    const i = ativos.value.indexOf(m); const j = i + passo;
    if (j < 0 || j >= ativos.value.length) return;
    const a = lista.value.indexOf(ativos.value[i]); const b = lista.value.indexOf(ativos.value[j]);
    [lista.value[a], lista.value[b]] = [lista.value[b], lista.value[a]];
    salvarOrdem();
}
</script>

<template>
    <Head title="Motivos de perda" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Motivos de perda" subtitulo="O que o vendedor escolhe ao perder um lead ou projeto. Sem motivos, ninguém registra uma perda.">
                <template #acoes>
                    <AppButton @click="abrir()"><template #icone><Plus /></template>Novo motivo</AppButton>
                </template>
            </PageHeader>

            <AppCard padding="none">
                <EmptyState v-if="ativos.length === 0" :icone="CircleX" titulo="Nenhum motivo disponível" descricao="Sem motivos, ninguém consegue registrar uma perda.">
                    <template #acao><AppButton @click="abrir()"><template #icone><Plus /></template>Novo motivo</AppButton></template>
                </EmptyState>
                <draggable v-else :list="lista" item-key="id" handle=".alca" :animation="150" ghost-class="opacity-40" class="divide-y divide-line" @end="salvarOrdem">
                    <template #item="{ element: m }">
                        <div v-if="!m.arquivado" class="flex items-center gap-2 px-3 py-2 hover:bg-bg-2/60">
                            <button type="button" class="alca inline-flex h-9 w-7 cursor-grab items-center justify-center rounded text-fg-disabled hover:text-fg-1 active:cursor-grabbing" aria-label="Arrastar para reordenar"><GripVertical :size="16" aria-hidden="true" /></button>
                            <span class="min-w-0 flex-1 truncate text-md font-medium text-fg-0">{{ m.descricao }}</span>
                            <AppBadge tom="ok">Ativo</AppBadge>
                            <component :is="m.total_perdas > 0 && pode('leads.view-all') ? Link : 'span'" :href="m.total_perdas > 0 && pode('leads.view-all') ? route('relatorios.perdas') : undefined" class="num hidden whitespace-nowrap text-xs text-fg-2 sm:inline" :class="m.total_perdas > 0 && pode('leads.view-all') ? 'hover:text-primary hover:underline' : ''">
                                {{ m.total_perdas === 0 ? 'Nunca usado' : `Usado em ${m.total_perdas} perda${m.total_perdas === 1 ? '' : 's'}` }}
                            </component>
                            <div class="flex items-center">
                                <IconButton :icone="ArrowUp" rotulo="Mover para cima" tamanho="sm" :disabled="ativos.indexOf(m) === 0" @click="mover(m, -1)" />
                                <IconButton :icone="ArrowDown" rotulo="Mover para baixo" tamanho="sm" :disabled="ativos.indexOf(m) === ativos.length - 1" @click="mover(m, 1)" />
                                <IconButton :icone="Pencil" rotulo="Editar motivo" tamanho="sm" @click="abrir(m)" />
                                <IconButton :icone="Archive" rotulo="Arquivar motivo" tamanho="sm" variante="danger" @click="arquivar(m)" />
                            </div>
                        </div>
                    </template>
                </draggable>
            </AppCard>

            <details v-if="arquivados.length" class="mt-6">
                <summary class="cursor-pointer text-sm font-medium text-fg-1">Arquivados ({{ arquivados.length }})</summary>
                <ul class="mt-2 divide-y divide-line rounded-lg border border-line bg-bg-1">
                    <li v-for="m in arquivados" :key="m.id" class="flex items-center gap-3 px-4 py-2.5">
                        <span class="min-w-0 flex-1 truncate text-md text-fg-1">{{ m.descricao }}</span>
                        <AppBadge tom="warn">Arquivado</AppBadge>
                        <span class="num hidden text-xs text-fg-2 sm:inline">{{ m.total_perdas }} perda(s)</span>
                        <AppButton variante="secondary" tamanho="sm" @click="restaurar(m)"><template #icone><ArchiveRestore /></template>Restaurar</AppButton>
                    </li>
                </ul>
            </details>
        </PageContainer>

        <AppModal :aberto="modal.aberto" :titulo="modal.motivo ? 'Editar motivo' : 'Novo motivo de perda'" tamanho="sm" @fechar="modal.aberto = false">
            <form id="form-motivo" @submit.prevent="salvar">
                <FormField rotulo="Descrição" obrigatorio :erro="modal.erros.descricao" dica="Curto e específico: “Preço”, “Escolheu concorrente”, “Sem resposta”.">
                    <AppInput v-model="modal.descricao" :maxlength="100" autofocus />
                </FormField>
            </form>
            <template #rodape>
                <AppButton variante="secondary" @click="modal.aberto = false">Cancelar</AppButton>
                <AppButton type="submit" form="form-motivo" :carregando="modal.salvando" :disabled="!modal.descricao.trim()">{{ modal.motivo ? 'Salvar' : 'Criar motivo' }}</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
