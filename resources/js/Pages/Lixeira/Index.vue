<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArchiveRestore, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppTabs from '@/Components/ui/AppTabs.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useListagem } from '@/Composables/useListagem';
import { useToast } from '@/Composables/useToast';
import { ehErroDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatBRL, formatarDataHora } from '@/utils/formatadores';

/** Leads e projetos excluídos que você enxerga. Restaurar devolve tudo como estava; nada é apagado de vez. */
const props = defineProps({
    lixeira: { type: Object, required: true }, // { data, meta, sort, filtros }
    agentes: { type: Array, default: () => [] },
});

const toast = useToast();
const lista = useListagem({ rota: 'lixeira', only: ['lixeira'], padrao: { per_page: 25, sort: 'deleted_at', dir: 'desc', tipo: 'leads' }, filtros: ['tipo', 'user_id'] });
const tipo = computed({ get: () => lista.estado.tipo ?? 'leads', set: (v) => { selecionadas.value = []; lista.aplicar({ tipo: v }); } });
const projetos = computed(() => tipo.value === 'projetos');
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.name })));
const selecionadas = ref([]);

const colunas = computed(() => [
    { key: 'nome', rotulo: projetos.value ? 'Projeto' : 'Lead', ordenavel: true, primaria: true },
    projetos.value ? { key: 'lead', rotulo: 'Lead' } : { key: 'estagio', rotulo: 'Estágio', ocultarAbaixo: 'sm' },
    ...(props.agentes.length ? [{ key: 'responsavel', rotulo: 'Responsável', ocultarAbaixo: 'lg' }] : []),
    { key: 'deleted_at', rotulo: 'Excluído em', ordenavel: true, ocultarAbaixo: 'md', formatar: (v) => formatarDataHora(v) },
    { key: 'excluido_por', rotulo: 'Excluído por', ocultarAbaixo: 'lg', formatar: (v) => v ?? '—' },
]);

const recarregar = () => router.reload({ only: ['lixeira'] });
async function restaurar(ids) {
    try {
        const { data } = await axios.post(route('api.lixeira.restaurar'), { tipo: tipo.value, ids });
        const extra = data.ignorados ? ` ${data.ignorados} ficou${data.ignorados === 1 ? '' : 'ram'} de fora (restaure o lead primeiro).` : '';
        toast.sucesso(data.message + extra);
        selecionadas.value = [];
        recarregar();
    } catch (e) {
        if (ehErroDeValidacao(e)) toast.erro(mensagemDeErro(e, 'Não foi possível restaurar.'));
    }
}
</script>

<template>
    <Head title="Lixeira" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Lixeira" subtitulo="Leads e projetos excluídos. Restaurar devolve tudo como estava." />
            <AppTabs v-model="tipo" :abas="[{ chave: 'leads', rotulo: 'Leads' }, { chave: 'projetos', rotulo: 'Projetos' }]" class="mb-4" />
            <FilterBar v-model:busca="lista.busca.value" :placeholder-busca="projetos ? 'Buscar projeto' : 'Buscar lead'" :ativos="lista.estado.user_id ? 1 : 0" class="mb-4" @limpar="lista.aplicar({ user_id: null })">
                <AppSelect v-if="agentes.length" :model-value="lista.estado.user_id" :opcoes="opcoesAgente" placeholder="Todos os responsáveis" tamanho="sm" aria-label="Responsável" class="md:w-48" @update:model-value="lista.aplicar({ user_id: $event })" />
            </FilterBar>

            <DataTable
                :id="`lixeira-${tipo}`"
                :colunas="colunas"
                :linhas="lixeira.data"
                :meta="lixeira.meta"
                :ordenacao="lixeira.sort"
                :carregando="lista.carregando.value"
                selecionavel
                :selecionadas="selecionadas"
                :linhas-clicaveis="false"
                :rotulo-da-linha="(l) => l.nome"
                :vazio="{ titulo: 'A lixeira está vazia', descricao: 'Leads e projetos excluídos aparecem aqui.', icone: Trash2 }"
                @update:ordenacao="lista.ordenarPor($event.por)"
                @update:pagina="lista.irParaPagina"
                @update:por-pagina="lista.porPagina"
                @update:selecionadas="(v) => (selecionadas = v)"
            >
                <template #acoes-em-massa>
                    <AppButton variante="secondary" tamanho="sm" @click="restaurar(selecionadas)"><template #icone><ArchiveRestore /></template>Restaurar {{ selecionadas.length }}</AppButton>
                </template>
                <template #celula-nome="{ linha }">
                    <div class="min-w-0">
                        <Link v-if="!projetos" :href="route('leads.show', linha.id)" class="block truncate font-medium text-fg-0 hover:text-primary">{{ linha.nome }}</Link>
                        <p v-else class="truncate font-medium text-fg-0">{{ linha.nome }}</p>
                        <p class="truncate text-sm text-fg-2">{{ projetos ? (linha.preco ? formatBRL(linha.preco) : '') : linha.email }}</p>
                    </div>
                </template>
                <template #celula-estagio="{ linha }">
                    <AppBadge v-if="linha.estagio" :cor="linha.estagio.tipo === 'aberto' ? linha.estagio.cor : null" :tom="linha.estagio.tipo === 'ganho' ? 'ok' : linha.estagio.tipo === 'perdido' ? 'danger' : 'neutral'" ponto>{{ linha.estagio.descricao }}</AppBadge>
                    <span v-else class="text-fg-disabled">—</span>
                </template>
                <template #celula-lead="{ linha }">
                    <span class="text-fg-0">{{ linha.lead?.nome ?? '—' }}</span>
                    <AppBadge v-if="linha.lead_na_lixeira" tom="warn" class="ml-2">Lead na lixeira</AppBadge>
                </template>
                <template #celula-responsavel="{ linha }">{{ linha.responsavel?.name ?? '—' }}</template>
                <template #acoes="{ linha }">
                    <IconButton :icone="ArchiveRestore" :rotulo="linha.lead_na_lixeira ? 'Restaure o lead primeiro' : 'Restaurar'" tamanho="sm" :disabled="linha.lead_na_lixeira" @click="restaurar([linha.id])" />
                </template>
            </DataTable>
        </PageContainer>
    </AuthenticatedLayout>
</template>
