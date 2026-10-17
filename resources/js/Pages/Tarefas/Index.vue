<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckSquare, Pencil, RotateCcw, SquareCheck, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import TarefaFormModal from '@/Components/leads/TarefaFormModal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagem } from '@/Composables/useListagem';
import { useToast } from '@/Composables/useToast';
import { diasDesde, formatarData, formatarDataHora, parseDataLocal } from '@/utils/formatadores';

/** Tarefas de todos os leads que o agente enxerga, por escopo de vencimento. */
const props = defineProps({
    tarefas: { type: Object, required: true }, // { data, meta, sort, filtros }
    escopo: { type: String, required: true },
    agentes: { type: Array, default: () => [] },
});

const toast = useToast();
const { confirmar } = useConfirm();
const veTodos = computed(() => props.agentes.length > 0);

const lista = useListagem({
    rota: 'tarefas.index',
    only: ['tarefas', 'escopo'],
    padrao: { per_page: 25, sort: 'data_limite', dir: 'asc', escopo: 'pendentes' },
    filtros: ['escopo', 'user_id'],
});

const ESCOPOS = [
    { chave: 'atrasadas', rotulo: 'Atrasadas' },
    { chave: 'hoje', rotulo: 'Hoje' },
    { chave: 'proximos_7', rotulo: 'Próximos 7 dias' },
    { chave: 'pendentes', rotulo: 'Pendentes' },
    { chave: 'concluidas', rotulo: 'Concluídas' },
];
const escopoAtual = computed(() => lista.estado.escopo ?? 'pendentes');
const concluidas = computed(() => escopoAtual.value === 'concluidas');
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.name })));

const colunas = computed(() => [
    { key: 'titulo', rotulo: 'Tarefa', ordenavel: true, primaria: true },
    { key: 'lead', rotulo: 'Lead' },
    { key: 'data_limite', rotulo: concluidas.value ? 'Concluída em' : 'Vencimento', ordenavel: true, ocultarAbaixo: 'sm' },
    ...(veTodos.value ? [{ key: 'responsavel', rotulo: 'Responsável', ocultarAbaixo: 'lg' }] : []),
]);

const situacao = (t) => {
    const dias = diasDesde(parseDataLocal(t.data_limite));
    if (dias > 0) return { tom: 'danger', rotulo: `Vencida há ${dias} dia${dias === 1 ? '' : 's'}` };
    if (dias === 0) return { tom: 'warn', rotulo: 'Hoje' };
    return { tom: 'neutral', rotulo: formatarData(t.data_limite) };
};

const vazio = computed(() => ({
    atrasadas: { titulo: 'Nenhuma tarefa atrasada', descricao: 'Bom sinal.', icone: CheckSquare },
    hoje: { titulo: 'Nada vence hoje', descricao: 'Veja as próximas em Pendentes.', icone: CheckSquare },
    proximos_7: { titulo: 'Nada nos próximos 7 dias', descricao: 'Crie follow-ups a partir do perfil de um lead.', icone: CheckSquare },
    pendentes: { titulo: 'Nenhuma tarefa pendente', descricao: 'Crie follow-ups a partir do perfil de um lead.', icone: CheckSquare },
    concluidas: { titulo: 'Nenhuma tarefa concluída', descricao: null, icone: CheckSquare },
}[escopoAtual.value]));

const recarregar = () => router.reload({ only: ['tarefas'] });
const selecionadas = ref([]);
const modal = ref({ aberto: false, tarefa: null });

async function concluir(t) {
    try {
        await axios.put(route('api.tarefas.update', t.id), { concluido: true });
        toast.sucesso('Tarefa concluída.', { acao: { rotulo: 'Desfazer', onClick: () => axios.put(route('api.tarefas.update', t.id), { concluido: false }).then(recarregar) } });
        recarregar();
    } catch { /* interceptor */ }
}
async function reabrir(t) {
    try {
        await axios.put(route('api.tarefas.update', t.id), { concluido: false });
        toast.sucesso('Tarefa reaberta.');
        recarregar();
    } catch { /* interceptor */ }
}
async function excluir(t) {
    const ok = await confirmar({ titulo: `Excluir “${t.titulo}”?`, mensagem: 'Ela some das pendentes e da timeline do lead. Não dá para desfazer.', rotuloConfirmar: 'Excluir', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.tarefas.destroy', t.id));
        toast.sucesso('Tarefa excluída.');
        recarregar();
    } catch { /* interceptor */ }
}
async function concluirEmMassa() {
    try {
        const { data } = await axios.post(route('api.tarefas.concluir'), { ids: selecionadas.value });
        toast.sucesso(data.message);
        selecionadas.value = [];
        recarregar();
    } catch { /* interceptor */ }
}
async function excluirEmMassa() {
    const n = selecionadas.value.length;
    const ok = await confirmar({ titulo: `Excluir ${n} tarefa${n === 1 ? '' : 's'}?`, mensagem: 'Não dá para desfazer.', rotuloConfirmar: `Excluir ${n}`, tom: 'danger' });
    if (!ok) return;
    try {
        const { data } = await axios.post(route('api.tarefas.excluir'), { ids: selecionadas.value });
        toast.sucesso(data.message);
        selecionadas.value = [];
        recarregar();
    } catch { /* interceptor */ }
}
</script>

<template>
    <Head title="Tarefas" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Tarefas" :subtitulo="`${Number(tarefas.meta.total).toLocaleString('pt-BR')} ${tarefas.meta.total === 1 ? 'tarefa' : 'tarefas'}`" />

            <div role="radiogroup" aria-label="Escopo" class="mb-4 inline-flex min-w-0 max-w-full overflow-x-auto rounded border border-line bg-bg-1 p-0.5">
                <button
                    v-for="e in ESCOPOS"
                    :key="e.chave"
                    type="button"
                    :aria-pressed="escopoAtual === e.chave ? 'true' : 'false'"
                    class="h-8 whitespace-nowrap rounded-sm px-2.5 text-sm font-medium transition-colors duration-1 ease-ui"
                    :class="escopoAtual === e.chave ? 'bg-primary-subtle text-primary' : 'text-fg-1 hover:text-fg-0'"
                    @click="lista.aplicar({ escopo: e.chave })"
                >
                    {{ e.rotulo }}
                </button>
            </div>

            <FilterBar v-model:busca="lista.busca.value" placeholder-busca="Buscar por título ou lead" :ativos="lista.estado.user_id ? 1 : 0" class="mb-4" @limpar="lista.aplicar({ user_id: null })">
                <AppSelect v-if="veTodos" :model-value="lista.estado.user_id" :opcoes="opcoesAgente" placeholder="Todos os responsáveis" tamanho="sm" aria-label="Responsável" class="md:w-48" @update:model-value="lista.aplicar({ user_id: $event })" />
            </FilterBar>

            <DataTable
                id="tarefas"
                :colunas="colunas"
                :linhas="tarefas.data"
                :meta="tarefas.meta"
                :ordenacao="tarefas.sort"
                :carregando="lista.carregando.value"
                selecionavel
                :selecionadas="selecionadas"
                :linhas-clicaveis="false"
                :rotulo-da-linha="(t) => t.titulo"
                :vazio="vazio"
                @update:ordenacao="lista.ordenarPor($event.por)"
                @update:pagina="lista.irParaPagina"
                @update:por-pagina="lista.porPagina"
                @update:selecionadas="(v) => (selecionadas = v)"
            >
                <template #acoes-em-massa>
                    <AppButton v-if="!concluidas" variante="secondary" tamanho="sm" @click="concluirEmMassa"><template #icone><SquareCheck /></template>Concluir {{ selecionadas.length }}</AppButton>
                    <AppButton variante="danger" tamanho="sm" @click="excluirEmMassa"><template #icone><Trash2 /></template>Excluir {{ selecionadas.length }}</AppButton>
                </template>
                <template #celula-titulo="{ linha }">
                    <div class="flex items-start gap-3">
                        <input v-if="!concluidas" type="checkbox" :aria-label="`Concluir ${linha.titulo}`" class="form-checkbox mt-0.5 h-4 w-4 rounded-sm border-line-2 text-primary focus:ring-focus/25" @change="concluir(linha)" />
                        <div class="min-w-0">
                            <p class="font-medium text-fg-0" :class="concluidas ? 'line-through text-fg-2' : ''">{{ linha.titulo }}</p>
                            <p v-if="linha.anotacao" class="truncate text-sm text-fg-2">{{ linha.anotacao }}</p>
                            <p class="text-sm text-fg-2 sm:hidden"><AppBadge :tom="concluidas ? 'neutral' : situacao(linha).tom">{{ concluidas ? formatarData(linha.concluido_em) : situacao(linha).rotulo }}</AppBadge></p>
                        </div>
                    </div>
                </template>
                <template #celula-lead="{ linha }">
                    <Link v-if="linha.lead" :href="route('leads.show', { usuario: linha.lead.id, aba: 'tarefas' })" class="font-medium text-fg-0 hover:text-primary">{{ linha.lead.nome }}</Link>
                    <span v-else class="text-fg-disabled">—</span>
                </template>
                <template #celula-data_limite="{ linha }">
                    <span v-if="concluidas" class="num whitespace-nowrap text-fg-1">{{ formatarDataHora(linha.concluido_em) || '—' }}</span>
                    <AppBadge v-else :tom="situacao(linha).tom">{{ situacao(linha).rotulo }}</AppBadge>
                </template>
                <template #celula-responsavel="{ linha }">{{ linha.lead?.responsavel?.name ?? '—' }}</template>
                <template #acoes="{ linha }">
                    <IconButton v-if="concluidas" :icone="RotateCcw" rotulo="Reabrir" tamanho="sm" @click="reabrir(linha)" />
                    <IconButton v-else :icone="Pencil" rotulo="Editar" tamanho="sm" @click="modal = { aberto: true, tarefa: linha }" />
                    <IconButton :icone="Trash2" rotulo="Excluir" tamanho="sm" variante="danger" @click="excluir(linha)" />
                </template>
            </DataTable>
        </PageContainer>

        <TarefaFormModal :aberto="modal.aberto" :lead-id="modal.tarefa?.usuario_id ?? 0" :tarefa="modal.tarefa" @fechar="modal.aberto = false" @salvo="recarregar" />
    </AuthenticatedLayout>
</template>
