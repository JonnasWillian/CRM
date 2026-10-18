<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowRightLeft, ChevronDown, ExternalLink, MessageCircle, Plus, Trash2, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppSwitch from '@/Components/ui/AppSwitch.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import DateRange from '@/Components/ui/DateRange.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import FilterChips from '@/Components/ui/FilterChips.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import LeadFormModal from '@/Components/leads/LeadFormModal.vue';
import TransferirDialog from '@/Components/leads/TransferirDialog.vue';
import { mensagemDeErro } from '@/utils/erros';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagem } from '@/Composables/useListagem';
import { usePerda } from '@/Composables/usePerda';
import { useToast } from '@/Composables/useToast';
import { diasDesde, formatBRL, formatarData, formatarRelativo } from '@/utils/formatadores';
import { formatarTelefone } from '@/utils/telefone';

/**
 * A grade de leads. Dados por props; cada filtro/ordem/página é um
 * `router.get` com `only: ['leads']` (useListagem). Seleção em massa por
 * ids ou "todos os N resultados" (manda os filtros para o servidor).
 */
const props = defineProps({
    leads: { type: Object, required: true }, // { data, meta, sort, filtros }
    estagios: { type: Array, required: true },
    funis: { type: Array, required: true },
    agentes: { type: Array, default: () => [] },
});

const toast = useToast();
const { confirmar } = useConfirm();
const { pedirPerda } = usePerda();
const pode = (p) => usePage().props.auth.permissions?.[p] === true;
const veTodos = computed(() => props.agentes.length > 0);
const DIAS_ESTAGNADO = 14;

const lista = useListagem({
    rota: 'leads.index',
    only: ['leads'],
    padrao: { per_page: 25, sort: 'created_at', dir: 'desc' },
    filtros: ['funil_id', 'estagios', 'situacao', 'user_id', 'de', 'ate', 'estagnados'],
});

// ── Filtros ──
const estagiosDoFiltro = computed(() => props.estagios.filter((e) => !lista.estado.funil_id || e.funil_id === lista.estado.funil_id));
const opcoesEstagio = computed(() => estagiosDoFiltro.value.map((e) => ({ valor: e.id, rotulo: e.descricao, cor: e.cor })));
const opcoesFunil = computed(() => props.funis.map((f) => ({ valor: f.id, rotulo: f.nome })));
const opcoesSituacao = [
    { valor: 'ativos', rotulo: 'Em aberto' },
    { valor: 'ganhos', rotulo: 'Ganhos' },
    { valor: 'perdidos', rotulo: 'Perdidos' },
    { valor: 'com_projeto_aberto', rotulo: 'Com projeto em aberto' },
    { valor: 'sem_projeto', rotulo: 'Sem projeto' },
];
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.name })));
const estagiosSelecionados = computed({
    get: () => (Array.isArray(lista.estado.estagios) ? lista.estado.estagios : lista.estado.estagios ? [lista.estado.estagios] : []),
    set: (v) => lista.aplicar({ estagios: v }),
});
const mudarFunil = (id) => lista.aplicar({ funil_id: id, estagios: [] });

// ── Colunas ──
const funilDe = (l) => props.funis.find((f) => f.id === l.funil_id);
const colunas = computed(() => [
    { key: 'nome', rotulo: 'Lead', ordenavel: true, primaria: true },
    { key: 'telefone', rotulo: 'Telefone', ocultarAbaixo: 'sm' },
    { key: 'estagio', rotulo: 'Estágio', ordenavel: true },
    { key: 'valor_estimado', rotulo: 'Valor estimado', ordenavel: true, alinhar: 'direita', ocultarAbaixo: 'md' },
    { key: 'previsao_fechamento', rotulo: 'Previsão', ordenavel: true, ocultarAbaixo: 'lg' },
    ...(veTodos.value ? [{ key: 'responsavel', rotulo: 'Responsável', ordenavel: true, ocultarAbaixo: 'lg' }] : []),
    { key: 'ultimo_contato', rotulo: 'Último contato', ordenavel: true, ocultarAbaixo: 'md' },
    { key: 'created_at', rotulo: 'Criado em', ordenavel: true, ocultarAbaixo: 'xl', formatar: (v) => formatarData(v) },
]);

const whatsapp = (l) => {
    const d = String(l.telefone ?? '').replace(/\D/g, '');
    return d.length >= 10 ? `https://wa.me/${d}` : null;
};
const previsaoVencida = (l) => l.previsao_fechamento && l.estagio?.tipo === 'aberto' && new Date(l.previsao_fechamento) < new Date(new Date().toDateString());
const estagnado = (l) => (diasDesde(l.ultimo_contato_em) ?? 0) >= DIAS_ESTAGNADO && l.estagio?.tipo === 'aberto';
const hrefDoLead = (l) => route('leads.show', l.id);

// ── Vazio ──
const vazio = computed(() => {
    if (lista.filtrosAtivos.value > 0 || lista.busca.value) return { titulo: 'Nenhum lead com esses filtros', descricao: 'Tente outro termo ou limpe os filtros.', icone: Users };
    if (!veTodos.value) return { titulo: 'Você ainda não tem leads', descricao: 'Cadastre um lead ou peça a um gestor para transferir leads para você.', icone: Users };
    return { titulo: 'Nenhum lead cadastrado ainda', descricao: 'Cadastre o primeiro ou importe uma planilha.', icone: Users };
});

// ── Novo lead ──
const modal = ref(false);
onMounted(() => {
    if (new URLSearchParams(window.location.search).has('novo')) modal.value = true;
});
const recarregar = () => router.reload({ only: ['leads'] });

// ── Seleção e ações em massa ──
const selecionadas = ref([]);
const todos = ref(false); // "todos os N resultados"
const totalSelecionado = computed(() => (todos.value ? props.leads.meta.total : selecionadas.value.length));
const corpoDaSelecao = () => (todos.value ? { todos: true, filtros: lista.query.value } : { ids: selecionadas.value });
const limparSelecao = () => { selecionadas.value = []; todos.value = false; };

// A frase vem do servidor ("3 leads movidos para “X”."); aqui só se explica o que ficou de fora.
const resumo = ({ message, ignorados }) => (ignorados ? `${message} ${ignorados} ignorado${ignorados === 1 ? '' : 's'} (outro funil, já no estágio ou fora da sua carteira).` : message);

async function moverEmMassa(estagio) {
    const enviar = (perda) => axios.post(route('api.leads.mover-estagio'), { ...corpoDaSelecao(), estagio_id: estagio.id, perda }, { silencioso: Boolean(perda) });
    if (estagio.tipo === 'perdido') {
        const ok = await pedirPerda({ titulo: `Perder ${totalSelecionado.value} lead${totalSelecionado.value === 1 ? '' : 's'}`, detalhe: 'O mesmo motivo vale para todos.', enviar: async (perda) => { const { data } = await enviar(perda); toast.sucesso(resumo(data)); } });
        if (ok) { limparSelecao(); recarregar(); }
        return;
    }
    try {
        const { data } = await enviar(null);
        toast.sucesso(resumo(data));
        limparSelecao();
        recarregar();
    } catch { /* interceptor */ }
}

async function excluirEmMassa() {
    const n = totalSelecionado.value;
    const ok = await confirmar({
        titulo: `Mover ${n} lead${n === 1 ? '' : 's'} para a lixeira?`,
        mensagem: 'Eles somem das listas e do pipeline, com projetos, tarefas, anotações e documentos. Você pode restaurá-los na Lixeira.',
        rotuloConfirmar: 'Mover para a lixeira',
        tom: 'danger',
    });
    if (!ok) return;
    try {
        const { data } = await axios.post(route('api.leads.lixeira'), corpoDaSelecao());
        toast.sucesso(resumo(data));
        limparSelecao();
        recarregar();
    } catch { /* interceptor */ }
}

async function excluir(l) {
    const ok = await confirmar({
        titulo: `Mover “${l.nome}” para a lixeira?`,
        mensagem: 'O lead some das listas e do pipeline, com projetos, tarefas, anotações e documentos. Você pode restaurá-lo na Lixeira.',
        rotuloConfirmar: 'Mover para a lixeira',
        tom: 'danger',
    });
    if (!ok) return;
    try {
        await axios.delete(route('api.leads.destroy', l.id));
        toast.sucesso(`“${l.nome}” movido para a lixeira.`, {
            acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.leads.restaurar', l.id)).then(() => { toast.sucesso(`“${l.nome}” restaurado.`); recarregar(); }) },
        });
        recarregar();
    } catch { /* interceptor */ }
}

// ── Transferir (1 lead pela linha ou a seleção) ──
const transferencia = ref({ aberto: false, lead: null, salvando: false, erro: '' });
const abrirTransferencia = (lead = null) => { transferencia.value = { aberto: true, lead, salvando: false, erro: '' }; };
const tituloTransferencia = computed(() => (transferencia.value.lead ? `Transferir “${transferencia.value.lead.nome}”` : `Transferir ${totalSelecionado.value} lead${totalSelecionado.value === 1 ? '' : 's'}`));
async function transferir({ user_id }) {
    const um = transferencia.value.lead;
    transferencia.value.salvando = true;
    transferencia.value.erro = '';
    try {
        const { data } = um
            ? await axios.patch(route('api.leads.responsavel', um.id), { user_id }, { silencioso: true })
            : await axios.post(route('api.leads.transferir'), { ...corpoDaSelecao(), user_id }, { silencioso: true });
        transferencia.value.aberto = false;
        if (um) {
            const anterior = um.user_id;
            toast.sucesso(data.message, { acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.leads.responsavel', um.id), { user_id: anterior }).then(recarregar) } });
        } else {
            toast.sucesso(resumo(data));
            limparSelecao();
        }
        recarregar();
    } catch (e) {
        transferencia.value.erro = mensagemDeErro(e, 'Não foi possível transferir.');
    } finally {
        transferencia.value.salvando = false;
    }
}

// ── Atalhos: N novo lead, / busca ──
function atalho(e) {
    const alvo = e.target;
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo?.tagName) || alvo?.isContentEditable) return;
    if (e.key === '/') { e.preventDefault(); document.querySelector('input[type="search"]')?.focus(); }
    else if (e.key === 'n' || e.key === 'N') { e.preventDefault(); modal.value = true; }
    else if (e.key === 'Escape' && (selecionadas.value.length || todos.value)) limparSelecao();
}
onMounted(() => window.addEventListener('keydown', atalho));
onBeforeUnmount(() => window.removeEventListener('keydown', atalho));
</script>

<template>
    <Head title="Leads" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Leads" :subtitulo="`${Number(leads.meta.total).toLocaleString('pt-BR')} ${leads.meta.total === 1 ? 'lead' : 'leads'}`">
                <template #acoes>
                    <AppButton v-if="pode('leads.manage')" @click="modal = true"><template #icone><Plus /></template>Novo lead</AppButton>
                </template>
            </PageHeader>

            <FilterBar v-model:busca="lista.busca.value" placeholder-busca="Buscar por nome, e-mail ou telefone" :ativos="lista.filtrosAtivos.value" class="mb-4" @limpar="lista.limpar">
                <AppSelect v-if="funis.length > 1" :model-value="lista.estado.funil_id" :opcoes="opcoesFunil" placeholder="Todos os funis" tamanho="sm" aria-label="Funil" class="md:w-44" @update:model-value="mudarFunil" />
                <FilterChips v-model="estagiosSelecionados" :opcoes="opcoesEstagio" multiplo rotulo="Estágio" />
                <AppSelect :model-value="lista.estado.situacao" :opcoes="opcoesSituacao" placeholder="Qualquer situação" tamanho="sm" aria-label="Situação" class="md:w-48" @update:model-value="lista.aplicar({ situacao: $event })" />
                <AppSelect v-if="veTodos" :model-value="lista.estado.user_id" :opcoes="opcoesAgente" placeholder="Todos os responsáveis" tamanho="sm" aria-label="Responsável" class="md:w-48" @update:model-value="lista.aplicar({ user_id: $event })" />
                <DateRange rotulo="Criado em" :de="lista.estado.de" :ate="lista.estado.ate" @update:de="lista.aplicar({ de: $event })" @update:ate="lista.aplicar({ ate: $event })" />
                <AppSwitch :model-value="Boolean(lista.estado.estagnados)" rotulo="Sem contato há +14 dias" @update:model-value="lista.aplicar({ estagnados: $event ? 1 : null })" />
            </FilterBar>

            <DataTable
                id="leads"
                :colunas="colunas"
                :linhas="leads.data"
                :meta="leads.meta"
                :ordenacao="leads.sort"
                :carregando="lista.carregando.value"
                :selecionavel="pode('leads.manage')"
                :selecionadas="selecionadas"
                :total-resultados="leads.meta.total"
                :href-da-linha="hrefDoLead"
                :rotulo-da-linha="(l) => l.nome"
                :vazio="vazio"
                @update:ordenacao="lista.ordenarPor($event.por)"
                @update:pagina="lista.irParaPagina"
                @update:por-pagina="lista.porPagina"
                @update:selecionadas="(v) => { selecionadas = v; if (!v.length) todos = false; }"
                @selecionar-todos="todos = true"
                @linha-clique="(l) => router.visit(hrefDoLead(l))"
            >
                <template #acoes-em-massa>
                    <span v-if="todos" class="num text-sm text-primary">Todos os {{ Number(leads.meta.total).toLocaleString('pt-BR') }} resultados</span>
                    <AppDropdown rotulo="Mover de estágio" alinhar="start">
                        <template #gatilho="{ alternar, atributos }">
                            <AppButton variante="secondary" tamanho="sm" v-bind="atributos" @click="alternar"><template #icone><ArrowRightLeft /></template>Mover de estágio<template #iconeDireita><ChevronDown /></template></AppButton>
                        </template>
                        <template v-for="f in funis" :key="f.id">
                            <p v-if="funis.length > 1" class="px-2.5 pb-1 pt-1.5 text-xs font-medium uppercase tracking-wider text-fg-2">{{ f.nome }}</p>
                            <AppDropdownItem v-for="e in estagios.filter((x) => x.funil_id === f.id)" :key="e.id" :rotulo="e.descricao" :tom="e.tipo === 'perdido' ? 'danger' : 'default'" @click="moverEmMassa(e)" />
                        </template>
                    </AppDropdown>
                    <AppButton v-if="veTodos" variante="secondary" tamanho="sm" @click="abrirTransferencia()"><template #icone><Users /></template>Transferir</AppButton>
                    <AppButton variante="danger" tamanho="sm" @click="excluirEmMassa"><template #icone><Trash2 /></template>Mover para a lixeira</AppButton>
                </template>

                <template #celula-nome="{ linha }">
                    <div class="flex min-w-0 items-center gap-3">
                        <AppAvatar :nome="linha.nome" :tamanho="32" class="hidden sm:inline-flex" />
                        <div class="min-w-0">
                            <Link :href="hrefDoLead(linha)" class="block truncate font-medium text-fg-0 hover:text-primary" @click.stop>{{ linha.nome }}</Link>
                            <p class="truncate text-sm text-fg-2">{{ linha.email }}</p>
                            <p class="truncate text-sm text-fg-2 sm:hidden">{{ linha.estagio?.descricao ?? '—' }}<span v-if="linha.valor_estimado"> · {{ formatBRL(linha.valor_estimado) }}</span></p>
                        </div>
                    </div>
                </template>
                <template #celula-telefone="{ linha }">
                    <span v-if="linha.telefone" class="inline-flex items-center gap-1 whitespace-nowrap">
                        {{ formatarTelefone(linha.telefone) }}
                        <IconButton v-if="whatsapp(linha)" :icone="MessageCircle" rotulo="Abrir no WhatsApp" tamanho="sm" as="a" :href="whatsapp(linha)" target="_blank" rel="noopener" @click.stop />
                    </span>
                    <span v-else class="text-fg-disabled">—</span>
                </template>
                <template #celula-estagio="{ linha }">
                    <div class="flex flex-col items-start gap-0.5">
                        <AppBadge v-if="linha.estagio" :cor="linha.estagio.tipo === 'aberto' ? linha.estagio.cor : null" :tom="linha.estagio.tipo === 'ganho' ? 'ok' : linha.estagio.tipo === 'perdido' ? 'danger' : 'neutral'" ponto>{{ linha.estagio.descricao }}</AppBadge>
                        <span v-else class="text-fg-disabled">—</span>
                        <span v-if="funis.length > 1 && funilDe(linha)" class="text-xs text-fg-2">{{ funilDe(linha).nome }}</span>
                    </div>
                </template>
                <template #celula-valor_estimado="{ linha }">
                    <span v-if="linha.valor_estimado" class="num whitespace-nowrap">{{ formatBRL(linha.valor_estimado) }}<span v-if="linha.probabilidade !== null && linha.probabilidade !== undefined" class="ml-1 text-xs text-fg-2">{{ linha.probabilidade }}%</span></span>
                    <span v-else class="text-fg-disabled">—</span>
                </template>
                <template #celula-previsao_fechamento="{ linha }">
                    <span v-if="linha.previsao_fechamento" class="num whitespace-nowrap" :class="previsaoVencida(linha) ? 'font-medium text-danger' : ''">{{ formatarData(linha.previsao_fechamento) }}</span>
                    <span v-else class="text-fg-disabled">—</span>
                </template>
                <template #celula-responsavel="{ linha }">{{ linha.responsavel?.name ?? '—' }}</template>
                <template #celula-ultimo_contato="{ linha }">
                    <span class="whitespace-nowrap" :class="estagnado(linha) ? 'font-medium text-warn' : ''">{{ formatarRelativo(linha.ultimo_contato_em) ?? '—' }}</span>
                </template>
                <template #acoes="{ linha }">
                    <IconButton :icone="ExternalLink" rotulo="Abrir" tamanho="sm" as="Link" :href="hrefDoLead(linha)" @click.stop />
                    <IconButton v-if="whatsapp(linha)" :icone="MessageCircle" rotulo="Abrir no WhatsApp" tamanho="sm" as="a" :href="whatsapp(linha)" target="_blank" rel="noopener" class="sm:hidden" @click.stop />
                    <IconButton v-if="veTodos" :icone="ArrowRightLeft" rotulo="Transferir" tamanho="sm" @click.stop="abrirTransferencia(linha)" />
                    <IconButton v-if="pode('leads.manage')" :icone="Trash2" rotulo="Mover para a lixeira" tamanho="sm" variante="danger" @click.stop="excluir(linha)" />
                </template>
                <template #vazio>
                    <AppButton v-if="lista.filtrosAtivos.value > 0 || lista.busca.value" variante="secondary" @click="lista.limpar">Limpar filtros</AppButton>
                    <AppButton v-else-if="pode('leads.manage')" @click="modal = true"><template #icone><Plus /></template>Novo lead</AppButton>
                </template>
            </DataTable>
        </PageContainer>

        <TransferirDialog :aberto="transferencia.aberto" :titulo="tituloTransferencia" :descricao="transferencia.lead ? null : 'Os leads saem das carteiras atuais; o histórico e as tarefas seguem com eles.'" :agentes="agentes" :atual-id="transferencia.lead?.user_id ?? null" :rotulo-confirmar="transferencia.lead ? 'Transferir' : `Transferir ${totalSelecionado} lead${totalSelecionado === 1 ? '' : 's'}`" :salvando="transferencia.salvando" :erro="transferencia.erro" @confirmar="transferir" @cancelar="transferencia.aberto = false" />
        <LeadFormModal :aberto="modal" :funis="funis" :estagios="estagios" :agentes="agentes" @fechar="modal = false" @salvo="limparSelecao" />
    </AuthenticatedLayout>
</template>
