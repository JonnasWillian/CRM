<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { ArrowRightLeft, Briefcase, CalendarClock, CheckCircle2, Clock, FileText, MessageSquare, Paperclip, RotateCcw, Tag, Trash2, Upload, UserPlus, UserRound } from 'lucide-vue-next';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterChips from '@/Components/ui/FilterChips.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import { mensagemDeErro } from '@/utils/erros';
import { formatarDataHora } from '@/utils/formatadores';

/** Histórico do lead (activity log), paginado por "Carregar mais"; filtro por tipo no servidor. */
const props = defineProps({ lead: { type: Object, required: true } });

const eventos = ref([]);
const carregando = ref(false);
const erro = ref(null);
const filtro = ref('todos');
const pagina = ref(0);
const ultimaPagina = ref(1);
const total = ref(0);

const temMais = computed(() => pagina.value < ultimaPagina.value);

const filtros = [
    { valor: 'todos', rotulo: 'Todos' },
    { valor: 'anotacao', rotulo: 'Anotações' },
    { valor: 'arquivo', rotulo: 'Arquivos' },
    { valor: 'projeto', rotulo: 'Projetos' },
    { valor: 'tarefa', rotulo: 'Tarefas' },
    { valor: 'estagio', rotulo: 'Estágio e funil' },
];

const TIPOS = {
    lead_criado: { tom: 'neutral', rotulo: 'Lead criado', icone: UserPlus },
    anotacao: { tom: 'info', rotulo: 'Anotação', icone: MessageSquare },
    arquivo: { tom: 'ok', rotulo: 'Arquivo enviado', icone: Paperclip },
    projeto: { tom: 'primary', rotulo: 'Projeto aberto', icone: Briefcase },
    projeto_anotacao: { tom: 'info', rotulo: 'Anotação no projeto', icone: FileText },
    projeto_anexo: { tom: 'ok', rotulo: 'Anexo no projeto', icone: Upload },
    status_alterado: { tom: 'warn', rotulo: 'Estágio alterado', icone: Tag },
    funil_alterado: { tom: 'warn', rotulo: 'Funil alterado', icone: ArrowRightLeft },
    responsavel_alterado: { tom: 'warn', rotulo: 'Responsável alterado', icone: UserRound },
    tarefa_criada: { tom: 'info', rotulo: 'Tarefa criada', icone: CalendarClock },
    tarefa_concluida: { tom: 'ok', rotulo: 'Tarefa concluída', icone: CheckCircle2 },
    anotacao_removida: { tom: 'neutral', rotulo: 'Anotação removida', icone: Trash2 },
    arquivo_removido: { tom: 'neutral', rotulo: 'Arquivo removido', icone: Trash2 },
    projeto_anotacao_removida: { tom: 'neutral', rotulo: 'Anotação de projeto removida', icone: Trash2 },
    projeto_anexo_removido: { tom: 'neutral', rotulo: 'Anexo de projeto removido', icone: Trash2 },
    projeto_removido: { tom: 'danger', rotulo: 'Projeto na lixeira', icone: Trash2 },
    projeto_restaurado: { tom: 'primary', rotulo: 'Projeto restaurado', icone: RotateCcw },
    lead_removido: { tom: 'danger', rotulo: 'Lead na lixeira', icone: Trash2 },
    lead_restaurado: { tom: 'neutral', rotulo: 'Lead restaurado', icone: RotateCcw },
};
const config = (tipo) => TIPOS[tipo] ?? { tom: 'neutral', rotulo: tipo, icone: Clock };

const titulo = (e) => ({
    lead_criado: 'Lead cadastrado no sistema',
    anotacao: 'Anotação registrada',
    arquivo: e.nome || 'Arquivo enviado',
    projeto: e.nome || 'Projeto aberto',
    projeto_anotacao: `Anotação em “${e.projeto_nome ?? 'projeto'}”`,
    projeto_anexo: `Arquivo em “${e.projeto_nome ?? 'projeto'}”`,
    status_alterado: `${e.estagio_anterior ?? '—'} → ${e.estagio_novo ?? '—'}`,
    funil_alterado: `Funil: ${e.estagio_anterior ?? '—'} → ${e.estagio_novo ?? '—'}`,
    responsavel_alterado: `${e.responsavel_anterior ?? '—'} → ${e.responsavel_novo ?? '—'}`,
    tarefa_criada: `Tarefa criada: “${e.titulo ?? ''}”`,
    tarefa_concluida: `Tarefa concluída: “${e.titulo ?? ''}”`,
    anotacao_removida: 'Anotação removida',
    arquivo_removido: e.nome ? `Arquivo removido: ${e.nome}` : 'Arquivo removido',
    projeto_anotacao_removida: 'Anotação de projeto removida',
    projeto_anexo_removido: e.nome ? `Anexo removido: ${e.nome}` : 'Anexo removido',
    projeto_removido: `Projeto na lixeira: “${e.nome ?? ''}”`,
    projeto_restaurado: `Projeto restaurado: “${e.nome ?? ''}”`,
    lead_removido: 'Lead movido para a lixeira',
    lead_restaurado: 'Lead restaurado',
}[e.tipo] ?? config(e.tipo).rotulo);

async function buscar(proxima = 1) {
    carregando.value = true;
    erro.value = null;
    try {
        const { data } = await axios.get(route('api.leads.atividades.index', props.lead.id), {
            params: { page: proxima, tipo: filtro.value === 'todos' ? undefined : filtro.value },
            silencioso: true,
        });
        eventos.value = proxima === 1 ? data.data : [...eventos.value, ...data.data];
        pagina.value = data.current_page;
        ultimaPagina.value = data.last_page;
        total.value = data.total;
    } catch (e) {
        erro.value = mensagemDeErro(e);
        if (proxima === 1) eventos.value = [];
    } finally {
        carregando.value = false;
    }
}

watch(filtro, () => buscar(1));
onMounted(() => buscar(1));
</script>

<template>
    <div class="space-y-4">
        <FilterChips v-model="filtro" :opcoes="filtros.map((f) => ({ ...f, contagem: f.valor === filtro ? total : undefined }))" rotulo="Mostrar" />

        <AppAlert v-if="erro" tom="danger" titulo="Não deu para carregar a timeline">
            {{ erro }}
            <template #acao><AppButton variante="secondary" tamanho="sm" @click="buscar(1)">Tentar de novo</AppButton></template>
        </AppAlert>
        <div v-else-if="carregando && !eventos.length" class="rounded-lg border border-line bg-bg-1"><SkeletonRows :linhas="5" :colunas="2" /></div>
        <EmptyState v-else-if="!eventos.length" :icone="Clock" titulo="Nada registrado ainda" descricao="As ações neste lead aparecem aqui." tamanho="sm" />
        <ol v-else class="relative space-y-3 border-l border-line pl-6">
            <li v-for="e in eventos" :key="e.id" class="relative">
                <span class="absolute -left-[31px] top-1 inline-flex h-6 w-6 items-center justify-center rounded-full border border-line bg-bg-1" :class="`text-${config(e.tipo).tom}`" aria-hidden="true">
                    <component :is="config(e.tipo).icone" :size="12" />
                </span>
                <div class="rounded-lg border border-line bg-bg-1 px-4 py-3 shadow-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <AppBadge :tom="config(e.tipo).tom">{{ config(e.tipo).rotulo }}</AppBadge>
                        <time class="num text-xs text-fg-2" :datetime="e.data">{{ formatarDataHora(e.data) }}</time>
                    </div>
                    <p class="mt-1.5 text-md font-medium text-fg-0">{{ titulo(e) }}</p>
                    <p v-if="e.descricao" class="mt-1 whitespace-pre-wrap text-sm text-fg-1">{{ e.descricao }}</p>
                </div>
            </li>
        </ol>
        <div v-if="temMais" class="flex justify-center">
            <AppButton variante="secondary" :carregando="carregando" @click="buscar(pagina + 1)">Carregar mais</AppButton>
        </div>
    </div>
</template>
