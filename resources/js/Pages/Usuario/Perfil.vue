<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Calendar, ChevronRight, CircleDollarSign, Clock, FileText, FolderOpen, Mail, MessageCircle, Paperclip, Percent, Phone, Plus, SquareCheck, Trash2, UserRound } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppTabs from '@/Components/ui/AppTabs.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AbaAnotacoes from '@/Components/leads/AbaAnotacoes.vue';
import AbaDados from '@/Components/leads/AbaDados.vue';
import AbaDocumentos from '@/Components/leads/AbaDocumentos.vue';
import MoverEstagioMenu from '@/Components/leads/MoverEstagioMenu.vue';
import ProjetoPanel from '@/Components/leads/ProjetoPanel.vue';
import TarefaPanel from '@/Components/leads/TarefaPanel.vue';
import TimelinePanel from '@/Components/leads/TimelinePanel.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { formatBRL, formatarData } from '@/utils/formatadores';
import { formatarTelefone } from '@/utils/telefone';

/**
 * Perfil do lead. Dados iniciais por props (Inertia); as abas carregam sob
 * demanda e ficam vivas (KeepAlive) enquanto a página existe.
 */
const props = defineProps({
    lead: { type: Object, required: true },
    funis: { type: Array, required: true },
    status: { type: Array, required: true },
});

const lead = ref(props.lead);
const { confirmar } = useConfirm();
const toast = useToast();
const pode = (p) => usePage().props.auth.permissions?.[p] === true;

// ── Abas ──
const ABAS = ['atividades', 'tarefas', 'anotacoes', 'projetos', 'documentos', 'dados'];
const abaInicial = new URLSearchParams(window.location.search).get('aba');
const aba = ref(ABAS.includes(abaInicial) ? abaInicial : 'atividades');
const abrirNovaTarefa = ref(0);

const abas = computed(() => [
    { chave: 'atividades', rotulo: 'Atividades', icone: Clock },
    { chave: 'tarefas', rotulo: 'Tarefas', icone: SquareCheck, contagem: lead.value.tarefas_pendentes_count ?? 0 },
    { chave: 'anotacoes', rotulo: 'Anotações', icone: FileText, contagem: lead.value.anotacoes_count ?? 0 },
    { chave: 'projetos', rotulo: 'Projetos', icone: FolderOpen, contagem: lead.value.projetos_count ?? 0 },
    { chave: 'documentos', rotulo: 'Documentos', icone: Paperclip, contagem: lead.value.arquivos_count ?? 0 },
    { chave: 'dados', rotulo: 'Dados', icone: UserRound },
]);

const componentes = { atividades: TimelinePanel, tarefas: TarefaPanel, anotacoes: AbaAnotacoes, projetos: ProjetoPanel, documentos: AbaDocumentos, dados: AbaDados };

// ── Cabeçalho ──
const whatsapp = computed(() => {
    const digitos = String(lead.value.telefone ?? '').replace(/\D/g, '');
    return digitos.length >= 10 ? `https://wa.me/${digitos}` : null;
});

const origem = computed(() => {
    const de = new URLSearchParams(window.location.search).get('de');
    if (de === 'pipeline') return { rotulo: 'Pipeline', href: route('kanban') };
    return route().has('leads.index') ? { rotulo: 'Leads', href: route('leads.index') } : { rotulo: 'Dashboard', href: route('dashboard') };
});

const atualizar = (novo) => { lead.value = { ...lead.value, ...novo }; };
const atualizarContagem = ({ chave, total }) => { lead.value = { ...lead.value, [`${chave}_count`]: total }; };

function novaTarefa() {
    aba.value = 'tarefas';
    abrirNovaTarefa.value++;
}

async function excluir() {
    const ok = await confirmar({
        titulo: `Mover “${lead.value.nome}” para a lixeira?`,
        mensagem: 'O lead some das listas e do pipeline, com projetos, tarefas, anotações e documentos. Você pode restaurá-lo na Lixeira.',
        rotuloConfirmar: 'Mover para a lixeira',
        tom: 'danger',
    });
    if (!ok) return;
    try {
        await axios.delete(route('api.leads.destroy', lead.value.id));
        toast.sucesso(`“${lead.value.nome}” movido para a lixeira.`, {
            acao: { rotulo: 'Desfazer', onClick: () => axios.patch(route('api.leads.restaurar', lead.value.id)).then(() => toast.sucesso(`“${lead.value.nome}” restaurado.`)) },
        });
        router.visit(origem.value.href);
    } catch {
        /* interceptor */
    }
}
</script>

<template>
    <Head :title="lead.nome" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader :titulo="lead.nome">
                <template #migalhas>
                    <Link :href="origem.href" class="hover:text-fg-0">{{ origem.rotulo }}</Link>
                    <ChevronRight :size="14" aria-hidden="true" />
                    <span class="truncate text-fg-1">{{ lead.nome }}</span>
                </template>
                <template #abaixo>
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-fg-1">
                        <MoverEstagioMenu :lead="lead" :funis="funis" @atualizado="atualizar" />
                        <span v-if="lead.funil" class="text-fg-2">Funil <b class="font-medium text-fg-1">{{ lead.funil.nome }}</b></span>
                        <span v-if="lead.responsavel" class="inline-flex items-center gap-1.5 text-fg-2"><UserRound :size="14" aria-hidden="true" /> {{ lead.responsavel.name }}</span>
                        <a :href="`mailto:${lead.email}`" class="inline-flex items-center gap-1.5 hover:text-primary"><Mail :size="14" aria-hidden="true" /> {{ lead.email }}</a>
                        <span v-if="lead.telefone" class="inline-flex items-center gap-1.5">
                            <a :href="`tel:${lead.telefone}`" class="inline-flex items-center gap-1.5 hover:text-primary"><Phone :size="14" aria-hidden="true" /> {{ formatarTelefone(lead.telefone) }}</a>
                            <IconButton v-if="whatsapp" :icone="MessageCircle" rotulo="Abrir no WhatsApp" tamanho="sm" as="a" :href="whatsapp" target="_blank" rel="noopener" />
                        </span>
                    </div>
                    <div v-if="lead.valor_estimado || lead.probabilidade !== null || lead.previsao_fechamento" class="mt-2 flex flex-wrap items-center gap-2">
                        <AppBadge v-if="lead.valor_estimado" tom="primary" tamanho="md"><CircleDollarSign :size="12" class="mr-1 inline" aria-hidden="true" />{{ formatBRL(lead.valor_estimado) }}</AppBadge>
                        <AppBadge v-if="lead.probabilidade !== null && lead.probabilidade !== undefined" tom="info" tamanho="md"><Percent :size="12" class="mr-1 inline" aria-hidden="true" />{{ lead.probabilidade }}% de chance</AppBadge>
                        <AppBadge v-if="lead.previsao_fechamento" :tom="new Date(lead.previsao_fechamento) < new Date() && lead.estagio?.tipo === 'aberto' ? 'danger' : 'neutral'" tamanho="md"><Calendar :size="12" class="mr-1 inline" aria-hidden="true" />Previsão {{ formatarData(lead.previsao_fechamento) }}</AppBadge>
                    </div>
                </template>
                <template #acoes>
                    <AppButton variante="secondary" @click="novaTarefa"><template #icone><Plus /></template>Nova tarefa</AppButton>
                    <AppDropdown rotulo="Mais ações" alinhar="end">
                        <AppDropdownItem rotulo="Mover para a lixeira" :icone="Trash2" tom="danger" @click="excluir" />
                    </AppDropdown>
                </template>
            </PageHeader>

            <div class="flex items-start gap-4">
                <AppAvatar :nome="lead.nome" :tamanho="40" class="hidden sm:inline-flex" />
                <div class="min-w-0 flex-1">
                    <AppTabs v-model="aba" :abas="abas" sincronizar-url="aba" />
                    <div class="mt-4" :id="`painel-${aba}`" role="tabpanel" :aria-labelledby="`aba-${aba}`">
                        <KeepAlive>
                            <component
                                :is="componentes[aba]"
                                :key="aba"
                                :lead="lead"
                                :status="status"
                                :funis="funis"
                                :abrir-formulario="abrirNovaTarefa"
                                @atualizado="atualizar"
                                @contagem="atualizarContagem"
                            />
                        </KeepAlive>
                    </div>
                </div>
            </div>
        </PageContainer>
    </AuthenticatedLayout>
</template>
