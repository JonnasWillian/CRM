<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { CalendarClock, LayoutTemplate, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCheckbox from '@/Components/ui/AppCheckbox.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppPagination from '@/Components/ui/AppPagination.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { useToast } from '@/Composables/useToast';
import { diasDesde, formatarData, formatarDataHora, parseDataLocal } from '@/utils/formatadores';
import TarefaFormModal from './TarefaFormModal.vue';

/**
 * Tarefas do lead. Concluir não pede confirmação (é reversível: toast com
 * Desfazer e "Reabrir" nas concluídas). Excluir pede.
 */
const props = defineProps({
    lead: { type: Object, required: true },
    abrirFormulario: { type: Number, default: 0 },
});
const emit = defineEmits(['contagem']);

const toast = useToast();
const { confirmar } = useConfirm();

const pendentes = useListagemAxios(() => route('api.leads.tarefas.index', props.lead.id), { params: { escopo: 'pendentes' }, padrao: { per_page: 50 } });
const concluidas = useListagemAxios(() => route('api.leads.tarefas.index', props.lead.id), { params: { escopo: 'concluidas' }, padrao: { per_page: 10 } });

const modelos = ref([]);
const modal = ref({ aberto: false, tarefa: null });
const dialogoModelos = ref({ aberto: false, selecionados: [], salvando: false });

const contagem = () => pendentes.meta.value && emit('contagem', { chave: 'tarefas_pendentes', total: pendentes.meta.value.total });
const recarregarTudo = async () => { await Promise.all([pendentes.recarregar(), concluidas.recarregar()]); contagem(); };

async function carregarModelos() {
    try {
        const { data } = await axios.get(route('api.tarefa-padroes.index'), { silencioso: true });
        modelos.value = data;
    } catch { modelos.value = []; }
}
onMounted(carregarModelos);

watch(() => props.abrirFormulario, (n) => { if (n) modal.value = { aberto: true, tarefa: null }; });

const situacao = (t) => {
    const dias = diasDesde(parseDataLocal(t.data_limite));
    if (dias > 0) return { tom: 'danger', rotulo: `Vencida há ${dias} dia${dias === 1 ? '' : 's'}` };
    if (dias === 0) return { tom: 'warn', rotulo: 'Hoje' };
    return { tom: 'neutral', rotulo: formatarData(t.data_limite) };
};

async function concluir(t) {
    try {
        await axios.put(route('api.tarefas.update', t.id), { concluido: true });
        toast.sucesso('Tarefa concluída.', { acao: { rotulo: 'Desfazer', onClick: () => reabrir(t, true) } });
        await recarregarTudo();
    } catch { /* interceptor */ }
}

async function reabrir(t, silencioso = false) {
    try {
        await axios.put(route('api.tarefas.update', t.id), { concluido: false });
        if (!silencioso) toast.sucesso('Tarefa reaberta.');
        await recarregarTudo();
    } catch { /* interceptor */ }
}

async function excluir(t) {
    const ok = await confirmar({ titulo: `Excluir “${t.titulo}”?`, mensagem: 'Ela some das pendentes e da timeline. Não dá para desfazer.', rotuloConfirmar: 'Excluir', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.tarefas.destroy', t.id));
        toast.sucesso('Tarefa excluída.');
        await recarregarTudo();
    } catch { /* interceptor */ }
}

const dataDoModelo = (m) => { const d = new Date(); d.setDate(d.getDate() + (Number(m.prazo_dias) || 0)); return d.toLocaleDateString('pt-BR'); };

async function aplicarModelos() {
    const ids = dialogoModelos.value.selecionados;
    if (!ids.length) return;
    dialogoModelos.value.salvando = true;
    try {
        await axios.post(route('api.leads.tarefas.aplicar-modelos', props.lead.id), { padroes: ids });
        toast.sucesso(`${ids.length} tarefa${ids.length === 1 ? '' : 's'} criada${ids.length === 1 ? '' : 's'} a partir dos modelos.`);
        dialogoModelos.value = { aberto: false, selecionados: [], salvando: false };
        await recarregarTudo();
    } catch {
        dialogoModelos.value.salvando = false;
    }
}

const carregando = computed(() => pendentes.carregando.value || concluidas.carregando.value);
</script>

<template>
    <div class="space-y-6">
        <SectionHeader titulo="Tarefas" :contagem="pendentes.meta.value?.total ?? null" descricao="Pendentes por vencimento. Concluir é reversível.">
            <template #acoes>
                <AppButton variante="secondary" :disabled="!modelos.length" @click="dialogoModelos = { aberto: true, selecionados: [], salvando: false }"><template #icone><LayoutTemplate /></template>Aplicar modelos</AppButton>
                <AppButton @click="modal = { aberto: true, tarefa: null }"><template #icone><Plus /></template>Nova tarefa</AppButton>
            </template>
        </SectionHeader>

        <AppAlert v-if="pendentes.erro.value" tom="danger" titulo="Não deu para carregar as tarefas">
            {{ pendentes.erro.value }}
            <template #acao><AppButton variante="secondary" tamanho="sm" @click="recarregarTudo">Tentar de novo</AppButton></template>
        </AppAlert>
        <div v-else-if="carregando && !pendentes.linhas.value.length && !concluidas.linhas.value.length" class="rounded-lg border border-line bg-bg-1"><SkeletonRows :linhas="3" :colunas="3" /></div>
        <EmptyState v-else-if="!pendentes.linhas.value.length && !concluidas.linhas.value.length" :icone="CalendarClock" titulo="Nenhuma tarefa" descricao="Crie um follow-up ou aplique um modelo.">
            <template #acao>
                <AppButton variante="secondary" :disabled="!modelos.length" @click="dialogoModelos = { aberto: true, selecionados: [], salvando: false }">Aplicar modelos</AppButton>
                <AppButton @click="modal = { aberto: true, tarefa: null }">Nova tarefa</AppButton>
            </template>
        </EmptyState>
        <template v-else>
            <ul v-if="pendentes.linhas.value.length" class="divide-y divide-line rounded-lg border border-line bg-bg-1 shadow-1" :class="pendentes.carregando.value ? 'opacity-60' : ''">
                <li v-for="t in pendentes.linhas.value" :key="t.id" class="flex items-start gap-3 px-4 py-3">
                    <AppCheckbox :model-value="false" :aria-label="`Concluir ${t.titulo}`" class="mt-0.5" @update:model-value="concluir(t)" />
                    <div class="min-w-0 flex-1">
                        <p class="text-md font-medium text-fg-0">{{ t.titulo }}</p>
                        <p v-if="t.anotacao" class="mt-0.5 line-clamp-2 text-sm text-fg-1">{{ t.anotacao }}</p>
                    </div>
                    <AppBadge :tom="situacao(t).tom" class="shrink-0">{{ situacao(t).rotulo }}</AppBadge>
                    <div class="flex shrink-0 items-center gap-0.5">
                        <IconButton :icone="Pencil" rotulo="Editar" tamanho="sm" @click="modal = { aberto: true, tarefa: t }" />
                        <IconButton :icone="Trash2" rotulo="Excluir" tamanho="sm" variante="danger" @click="excluir(t)" />
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-fg-2">Nenhuma tarefa pendente.</p>

            <section v-if="concluidas.linhas.value.length">
                <h3 class="mb-2 text-sm font-medium text-fg-2">Concluídas <span class="num">({{ concluidas.meta.value?.total ?? concluidas.linhas.value.length }})</span></h3>
                <ul class="divide-y divide-line rounded-lg border border-line bg-bg-0/60">
                    <li v-for="t in concluidas.linhas.value" :key="t.id" class="flex items-center gap-3 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-md text-fg-1 line-through decoration-fg-disabled">{{ t.titulo }}</p>
                            <p v-if="t.concluido_em" class="num text-xs text-fg-2">Concluída em {{ formatarDataHora(t.concluido_em) }}</p>
                        </div>
                        <IconButton :icone="RotateCcw" rotulo="Reabrir" tamanho="sm" @click="reabrir(t)" />
                        <IconButton :icone="Trash2" rotulo="Excluir" tamanho="sm" variante="danger" @click="excluir(t)" />
                    </li>
                </ul>
                <AppPagination v-if="concluidas.meta.value && concluidas.meta.value.last_page > 1" class="mt-2" :meta="concluidas.meta.value" :opcoes-por-pagina="[10, 25, 50]" @update:pagina="concluidas.irParaPagina" @update:por-pagina="concluidas.porPagina" />
            </section>
        </template>

        <TarefaFormModal :aberto="modal.aberto" :lead-id="lead.id" :tarefa="modal.tarefa" :modelos="modelos" @fechar="modal.aberto = false" @salvo="recarregarTudo" />

        <AppModal :aberto="dialogoModelos.aberto" titulo="Aplicar modelos de tarefa" descricao="A data de cada tarefa é calculada a partir de hoje." tamanho="sm" @fechar="dialogoModelos.aberto = false">
            <ul class="divide-y divide-line rounded border border-line">
                <li v-for="m in modelos" :key="m.id" class="px-3 py-2">
                    <AppCheckbox v-model="dialogoModelos.selecionados" :valor="m.id">
                        <span class="text-fg-0">{{ m.titulo }}</span> <span class="num text-sm text-fg-2">· {{ dataDoModelo(m) }}</span>
                    </AppCheckbox>
                </li>
            </ul>
            <template #rodape>
                <AppButton variante="secondary" @click="dialogoModelos.aberto = false">Cancelar</AppButton>
                <AppButton :disabled="!dialogoModelos.selecionados.length" :carregando="dialogoModelos.salvando" @click="aplicarModelos">Criar {{ dialogoModelos.selecionados.length || '' }} tarefa{{ dialogoModelos.selecionados.length === 1 ? '' : 's' }}</AppButton>
            </template>
        </AppModal>
    </div>
</template>
