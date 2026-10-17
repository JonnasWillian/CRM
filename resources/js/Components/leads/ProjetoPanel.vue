<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Calendar, ChevronDown, CreditCard, FolderOpen, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppPagination from '@/Components/ui/AppPagination.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { useToast } from '@/Composables/useToast';
import { formatBRL, formatarData } from '@/utils/formatadores';
import ProjetoDetalhe from './ProjetoDetalhe.vue';
import ProjetoFormModal from './ProjetoFormModal.vue';

/**
 * Projetos (propostas) do lead. A cor do status é semântica — aberto
 * neutro, ganho verde, perdido vermelho — e não um mapa por id: id não
 * significa nada entre tenants.
 */
const props = defineProps({
    lead: { type: Object, required: true },
    status: { type: Array, required: true },
});
const emit = defineEmits(['contagem']);

const toast = useToast();
const { confirmar } = useConfirm();
const lista = useListagemAxios(() => route('api.leads.projetos.index', props.lead.id), { padrao: { per_page: 20 } });

const modal = ref({ aberto: false, projeto: null });
const expandido = ref(null);
const contagensDetalhe = ref({});

const tomDoStatus = (s) => (s?.is_won ? 'ok' : s?.is_lost ? 'danger' : 'info');

const totais = computed(() => {
    const linhas = lista.linhas.value;
    const soma = (f) => linhas.filter(f).reduce((acc, p) => acc + (Number(p.preco) || 0), 0);
    return {
        aberto: soma((p) => !p.status?.is_won && !p.status?.is_lost),
        ganho: soma((p) => p.status?.is_won),
    };
});

const contagem = () => lista.meta.value && emit('contagem', { chave: 'projetos', total: lista.meta.value.total });

async function aposSalvar() {
    await lista.recarregar();
    contagem();
}

async function excluir(projeto) {
    const ok = await confirmar({
        titulo: `Mover “${projeto.nome}” para a lixeira?`,
        mensagem: 'Anotações e anexos vão junto e voltam se você restaurar.',
        rotuloConfirmar: 'Mover para a lixeira',
        tom: 'danger',
    });
    if (!ok) return;
    try {
        await axios.delete(route('api.projetos.destroy', projeto.id));
        if (expandido.value === projeto.id) expandido.value = null;
        toast.sucesso(`“${projeto.nome}” movido para a lixeira.`, {
            acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.projetos.restaurar', projeto.id)).then(aposSalvar) },
        });
        await aposSalvar();
    } catch { /* interceptor */ }
}
</script>

<template>
    <div class="space-y-4">
        <SectionHeader titulo="Projetos" :contagem="lista.meta.value?.total ?? null" :descricao="lista.linhas.value.length ? `${formatBRL(totais.aberto)} em aberto · ${formatBRL(totais.ganho)} ganhos nesta página` : null">
            <template #acoes>
                <AppButton @click="modal = { aberto: true, projeto: null }"><template #icone><Plus /></template>Novo projeto</AppButton>
            </template>
        </SectionHeader>

        <AppAlert v-if="lista.erro.value" tom="danger" titulo="Não deu para carregar os projetos">
            {{ lista.erro.value }}
            <template #acao><AppButton variante="secondary" tamanho="sm" @click="lista.recarregar">Tentar de novo</AppButton></template>
        </AppAlert>
        <div v-else-if="lista.carregando.value && !lista.linhas.value.length" class="rounded-lg border border-line bg-bg-1"><SkeletonRows :linhas="3" :colunas="3" /></div>
        <EmptyState v-else-if="!lista.linhas.value.length" :icone="FolderOpen" titulo="Nenhuma proposta ainda" descricao="Crie um projeto quando houver negociação concreta.">
            <template #acao><AppButton variante="secondary" @click="modal = { aberto: true, projeto: null }">Novo projeto</AppButton></template>
        </EmptyState>
        <ul v-else class="space-y-2" :class="lista.carregando.value ? 'opacity-60' : ''">
            <li v-for="p in lista.linhas.value" :key="p.id" class="rounded-lg border border-line bg-bg-1 shadow-1">
                <div class="flex items-start gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-md font-semibold text-fg-0">{{ p.nome }}</h3>
                            <AppBadge :tom="tomDoStatus(p.status)" ponto>{{ p.status?.descricao ?? '—' }}</AppBadge>
                        </div>
                        <p v-if="p.descricao" class="mt-1 line-clamp-2 text-sm text-fg-1">{{ p.descricao }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-fg-2">
                            <span v-if="p.preco" class="num font-medium text-fg-0">{{ formatBRL(p.preco) }}</span>
                            <span v-if="p.data_inicial || p.data_final" class="inline-flex items-center gap-1"><Calendar :size="13" aria-hidden="true" />{{ formatarData(p.data_inicial) ?? '…' }} → {{ formatarData(p.data_final) ?? '…' }}</span>
                            <span v-if="p.parcelas && p.qtd_parcelas" class="inline-flex items-center gap-1"><CreditCard :size="13" aria-hidden="true" />{{ p.qtd_parcelas }}x</span>
                            <span class="num">{{ contagensDetalhe[p.id]?.anotacoes ?? p.anotacoes_count ?? 0 }} anotações · {{ contagensDetalhe[p.id]?.anexos ?? p.anexos_count ?? 0 }} anexos</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-0.5">
                        <IconButton :icone="ChevronDown" :rotulo="expandido === p.id ? 'Recolher' : 'Anotações e anexos'" :aria-expanded="expandido === p.id ? 'true' : 'false'" :class="{ 'rotate-180': expandido === p.id }" @click="expandido = expandido === p.id ? null : p.id" />
                        <IconButton :icone="Pencil" rotulo="Editar" @click="modal = { aberto: true, projeto: p }" />
                        <IconButton :icone="Trash2" rotulo="Mover para a lixeira" variante="danger" @click="excluir(p)" />
                    </div>
                </div>
                <ProjetoDetalhe v-if="expandido === p.id" :projeto="p" @contagens="contagensDetalhe[p.id] = $event" />
            </li>
        </ul>
        <AppPagination v-if="lista.meta.value && lista.meta.value.last_page > 1" :meta="lista.meta.value" :opcoes-por-pagina="[20, 50, 100]" :carregando="lista.carregando.value" @update:pagina="lista.irParaPagina" @update:por-pagina="lista.porPagina" />

        <ProjetoFormModal :aberto="modal.aberto" :lead-id="lead.id" :status="status" :projeto="modal.projeto" @fechar="modal.aberto = false" @salvo="aposSalvar" />
    </div>
</template>
