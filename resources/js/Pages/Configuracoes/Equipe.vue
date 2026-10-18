<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowRightLeft, Mail, MailPlus, RotateCcw, Send, UserCheck, UserCog, UserMinus, X } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppAvatar from '@/Components/ui/AppAvatar.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppTabs from '@/Components/ui/AppTabs.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import TransferirDialog from '@/Components/leads/TransferirDialog.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatarData } from '@/utils/formatadores';

/** Equipe: membros (ativos/inativos) e convites. Escritas pela API; a página recarrega as listas. */
const props = defineProps({
    membros: { type: Array, required: true },
    convites: { type: Array, required: true },
    papeis: { type: Array, required: true }, // [{ valor, rotulo, descricao }]
});

const toast = useToast();
const { confirmar } = useConfirm();
const eu = computed(() => usePage().props.auth.user);
const recarregar = () => router.reload({ only: ['membros', 'convites'] });

const ativos = computed(() => props.membros.filter((m) => m.ativo));
const inativos = computed(() => props.membros.filter((m) => !m.ativo));
const abaInicial = new URLSearchParams(window.location.search).get('aba');
const aba = ref(['ativos', 'inativos', 'convites'].includes(abaInicial) ? abaInicial : 'ativos');
const abas = computed(() => [
    { chave: 'ativos', rotulo: 'Ativos', contagem: ativos.value.length },
    { chave: 'inativos', rotulo: 'Inativos', contagem: inativos.value.length },
    { chave: 'convites', rotulo: 'Convites', contagem: props.convites.length },
]);
const rotuloPapel = (v) => props.papeis.find((p) => p.valor === v)?.rotulo ?? v ?? '—';
const opcoesPapel = computed(() => props.papeis.map((p) => ({ valor: p.valor, rotulo: p.rotulo })));
const subtitulo = computed(() => `${ativos.value.length} membro${ativos.value.length === 1 ? '' : 's'}${props.convites.length ? ` · ${props.convites.length} convite${props.convites.length === 1 ? '' : 's'} pendente${props.convites.length === 1 ? '' : 's'}` : ''}`);

const colunasMembros = [
    { key: 'name', rotulo: 'Membro', primaria: true },
    { key: 'papel', rotulo: 'Papel' },
    { key: 'leads_count', rotulo: 'Leads', alinhar: 'direita', ocultarAbaixo: 'sm' },
    { key: 'created_at', rotulo: 'Entrou em', ocultarAbaixo: 'md', formatar: (v) => formatarData(v) },
];
const colunasConvites = [
    { key: 'email', rotulo: 'E-mail', primaria: true },
    { key: 'papel', rotulo: 'Papel', formatar: (v) => rotuloPapel(v) },
    { key: 'enviado_em', rotulo: 'Enviado em', ocultarAbaixo: 'sm', formatar: (v) => formatarData(v) },
    { key: 'expira_em', rotulo: 'Expira em', ocultarAbaixo: 'md' },
];

// ── Convidar ──
const convite = reactive({ aberto: false, email: '', papel: 'vendedor', erros: {}, salvando: false });
const descricaoPapel = computed(() => props.papeis.find((p) => p.valor === convite.papel)?.descricao);
async function convidar() {
    convite.salvando = true;
    convite.erros = {};
    try {
        const { data } = await axios.post(route('api.equipe.convites.store'), { email: convite.email, papel: convite.papel }, { silencioso: true });
        toast.sucesso(data.message);
        convite.aberto = false;
        aba.value = 'convites';
        recarregar();
    } catch (e) {
        convite.erros = errosDeValidacao(e);
        if (!Object.keys(convite.erros).length) toast.erro(mensagemDeErro(e, 'Não foi possível enviar o convite.'));
    } finally {
        convite.salvando = false;
    }
}
async function reenviar(c) {
    try { const { data } = await axios.post(route('api.equipe.convites.reenviar', c.id)); toast.sucesso(data.message); recarregar(); } catch { /* interceptor */ }
}
async function cancelarConvite(c) {
    const ok = await confirmar({ titulo: `Cancelar o convite para ${c.email}?`, mensagem: 'O link enviado deixa de funcionar.', rotuloConfirmar: 'Cancelar convite', rotuloCancelar: 'Manter', tom: 'danger' });
    if (!ok) return;
    try { await axios.delete(route('api.equipe.convites.destroy', c.id)); toast.sucesso('Convite cancelado.'); recarregar(); } catch { /* interceptor */ }
}

// ── Papel ──
async function mudarPapel(m, papel) {
    if (papel === m.papel) return;
    const rebaixa = m.papel === 'admin' || (m.papel === 'gestor' && papel === 'vendedor');
    if (rebaixa) {
        const ok = await confirmar({
            titulo: `Mudar “${m.name}” de ${rotuloPapel(m.papel)} para ${rotuloPapel(papel)}?`,
            mensagem: papel === 'vendedor' ? 'Passa a ver só os próprios leads.' : 'Perde o acesso à equipe.',
            rotuloConfirmar: 'Alterar papel',
            tom: 'primary',
        });
        if (!ok) return recarregar();
    }
    try { const { data } = await axios.patch(route('api.equipe.papel', m.id), { papel }); toast.sucesso(data.message); } catch { /* interceptor */ }
    recarregar();
}

// ── Desativar (com transferência da carteira) ──
const desativacao = reactive({ aberto: false, membro: null, destino: null, erro: '', salvando: false });
const destinos = computed(() => ativos.value.filter((m) => m.id !== desativacao.membro?.id).map((m) => ({ valor: m.id, rotulo: m.name })));
function abrirDesativar(m) { Object.assign(desativacao, { aberto: true, membro: m, destino: null, erro: '', salvando: false }); }
async function desativar() {
    desativacao.salvando = true;
    desativacao.erro = '';
    try {
        const { data } = await axios.patch(route('api.equipe.desativar', desativacao.membro.id), { transferir_para: desativacao.destino }, { silencioso: true });
        toast.sucesso(data.message);
        desativacao.aberto = false;
        recarregar();
    } catch (e) {
        desativacao.erro = mensagemDeErro(e, 'Não foi possível desativar.');
    } finally {
        desativacao.salvando = false;
    }
}
async function reativar(m) {
    try { const { data } = await axios.patch(route('api.equipe.reativar', m.id)); toast.sucesso(data.message); recarregar(); } catch { /* interceptor */ }
}

// ── Transferir a carteira (sem desativar) ──
const carteira = reactive({ aberto: false, membro: null, salvando: false, erro: '' });
async function transferirCarteira({ user_id }) {
    carteira.salvando = true;
    carteira.erro = '';
    try {
        const { data } = await axios.post(route('api.leads.transferir'), { todos: true, filtros: { user_id: carteira.membro.id }, user_id }, { silencioso: true });
        toast.sucesso(data.message);
        carteira.aberto = false;
        recarregar();
    } catch (e) {
        carteira.erro = mensagemDeErro(e, 'Não foi possível transferir a carteira.');
    } finally {
        carteira.salvando = false;
    }
}
const agentesAtivos = computed(() => ativos.value.map((m) => ({ id: m.id, name: m.name })));
const expira = (c) => (c.estado === 'expirado' ? { tom: 'danger', rotulo: 'Expirado' } : { tom: 'neutral', rotulo: formatarData(c.expira_em) });
</script>

<template>
    <Head title="Equipe" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Equipe" :subtitulo="subtitulo">
                <template #acoes>
                    <AppButton @click="Object.assign(convite, { aberto: true, email: '', papel: 'vendedor', erros: {}, salvando: false })"><template #icone><MailPlus /></template>Convidar membro</AppButton>
                </template>
            </PageHeader>

            <AppTabs v-model="aba" :abas="abas" sincronizar-url="aba" class="mb-4" />

            <DataTable v-if="aba !== 'convites'" :id="`equipe-${aba}`" :colunas="colunasMembros" :linhas="aba === 'ativos' ? ativos : inativos" :meta="null" :linhas-clicaveis="false" :cabecalho-fixo="false" :rotulo-da-linha="(m) => m.name" :vazio="aba === 'ativos' ? { titulo: 'Nenhum membro ativo', icone: UserCog } : { titulo: 'Ninguém desativado', icone: UserCog }">
                <template #celula-name="{ linha }">
                    <div class="flex min-w-0 items-center gap-3">
                        <AppAvatar :nome="linha.name" :tamanho="32" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-fg-0">{{ linha.name }} <span v-if="linha.id === eu?.id" class="text-sm font-normal text-fg-2">(você)</span></p>
                            <p class="truncate text-sm text-fg-2">{{ linha.email }}</p>
                        </div>
                    </div>
                </template>
                <template #celula-papel="{ linha }">
                    <AppSelect v-if="linha.ativo && linha.id !== eu?.id" :model-value="linha.papel" :opcoes="opcoesPapel" tamanho="sm" :aria-label="`Papel de ${linha.name}`" class="w-32" @update:model-value="mudarPapel(linha, $event)" />
                    <AppBadge v-else :tom="linha.papel === 'admin' ? 'primary' : 'neutral'">{{ rotuloPapel(linha.papel) }}</AppBadge>
                </template>
                <template #celula-leads_count="{ linha }">
                    <Link v-if="linha.leads_count" :href="route('leads.index', { user_id: linha.id })" class="num font-medium text-primary hover:underline">{{ linha.leads_count }}</Link>
                    <span v-else class="num text-fg-2">0</span>
                </template>
                <template #acoes="{ linha }">
                    <template v-if="linha.ativo">
                        <IconButton v-if="linha.leads_count" :icone="ArrowRightLeft" rotulo="Transferir leads…" tamanho="sm" @click="Object.assign(carteira, { aberto: true, membro: linha, salvando: false, erro: '' })" />
                        <IconButton v-if="linha.id !== eu?.id" :icone="UserMinus" rotulo="Desativar" tamanho="sm" variante="danger" @click="abrirDesativar(linha)" />
                    </template>
                    <AppButton v-else variante="secondary" tamanho="sm" @click="reativar(linha)"><template #icone><UserCheck /></template>Reativar</AppButton>
                </template>
            </DataTable>

            <DataTable v-else id="equipe-convites" :colunas="colunasConvites" :linhas="convites" :meta="null" :linhas-clicaveis="false" :cabecalho-fixo="false" :vazio="{ titulo: 'Nenhum convite pendente', icone: Mail }">
                <template #celula-expira_em="{ linha }"><AppBadge :tom="expira(linha).tom">{{ expira(linha).rotulo }}</AppBadge></template>
                <template #acoes="{ linha }">
                    <IconButton :icone="Send" rotulo="Reenviar" tamanho="sm" @click="reenviar(linha)" />
                    <IconButton :icone="X" rotulo="Cancelar convite" tamanho="sm" variante="danger" @click="cancelarConvite(linha)" />
                </template>
                <template #vazio>
                    <AppButton variante="secondary" @click="convite.aberto = true"><template #icone><MailPlus /></template>Convidar membro</AppButton>
                </template>
            </DataTable>
        </PageContainer>

        <AppModal :aberto="convite.aberto" titulo="Convidar membro" descricao="A pessoa recebe um link por e-mail para criar o acesso. O convite vale 7 dias." tamanho="sm" @fechar="convite.aberto = false">
            <form id="form-convite" class="space-y-4" @submit.prevent="convidar">
                <FormField rotulo="E-mail" obrigatorio :erro="convite.erros.email">
                    <AppInput v-model="convite.email" type="email" autocomplete="off" autofocus />
                </FormField>
                <FormField rotulo="Papel" obrigatorio :erro="convite.erros.papel" :dica="descricaoPapel">
                    <AppSelect v-model="convite.papel" :opcoes="opcoesPapel" />
                </FormField>
            </form>
            <template #rodape>
                <AppButton variante="secondary" @click="convite.aberto = false">Cancelar</AppButton>
                <AppButton type="submit" form="form-convite" :carregando="convite.salvando" :disabled="!convite.email.trim()">Enviar convite</AppButton>
            </template>
        </AppModal>

        <AppModal :aberto="desativacao.aberto" :titulo="`Desativar “${desativacao.membro?.name ?? ''}”?`" tamanho="sm" @fechar="desativacao.aberto = false">
            <div class="space-y-4">
                <p class="text-md text-fg-1">Perde o acesso agora e as sessões abertas são encerradas. Você pode reativar depois.</p>
                <FormField v-if="desativacao.membro?.leads_count" rotulo="Transferir a carteira para" obrigatorio :dica="`${desativacao.membro.leads_count} lead(s) mudam de responsável; o histórico fica.`">
                    <AppSelect v-model="desativacao.destino" :opcoes="destinos" placeholder="Escolha um membro" />
                </FormField>
                <AppAlert v-if="desativacao.erro" tom="danger">{{ desativacao.erro }}</AppAlert>
            </div>
            <template #rodape>
                <AppButton variante="secondary" @click="desativacao.aberto = false">Cancelar</AppButton>
                <AppButton variante="danger" :carregando="desativacao.salvando" :disabled="Boolean(desativacao.membro?.leads_count) && !desativacao.destino" @click="desativar">Desativar</AppButton>
            </template>
        </AppModal>

        <TransferirDialog :aberto="carteira.aberto" :titulo="`Transferir os ${carteira.membro?.leads_count ?? 0} leads de “${carteira.membro?.name ?? ''}”`" descricao="Todos os leads saem da carteira desta pessoa; o histórico e as tarefas seguem com eles." :agentes="agentesAtivos" :atual-id="carteira.membro?.id ?? null" :rotulo-confirmar="`Transferir ${carteira.membro?.leads_count ?? 0} leads`" :salvando="carteira.salvando" :erro="carteira.erro" @confirmar="transferirCarteira" @cancelar="carteira.aberto = false" />
    </AuthenticatedLayout>
</template>
