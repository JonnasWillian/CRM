<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronRight, Download, FileUp, Upload } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import FormField from '@/Components/ui/FormField.vue';
import KpiCard from '@/Components/ui/KpiCard.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionHeader from '@/Components/ui/SectionHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatarDataHora } from '@/utils/formatadores';

/**
 * Importar planilha em 4 passos: Arquivo → Mapeamento → Processando →
 * Resultado. O passo e a importação ficam na URL (?id=), então dá para sair
 * durante o processamento e voltar depois.
 */
const props = defineProps({
    importacoes: { type: Array, default: () => [] },
    funis: { type: Array, required: true },
    estagios: { type: Array, required: true },
    agentes: { type: Array, default: () => [] },
    campos: { type: Array, required: true },
});

const toast = useToast();
const { confirmar } = useConfirm();

const ROTULOS = {
    nome: 'Nome', email: 'E-mail', telefone: 'Telefone', descricao: 'Descrição', funil: 'Funil', estagio: 'Estágio',
    valor_estimado: 'Valor estimado', probabilidade: 'Probabilidade (%)', previsao_fechamento: 'Previsão de fechamento', responsavel: 'Responsável (e-mail ou nome)',
};
const PASSOS = [{ chave: 'arquivo', rotulo: 'Arquivo' }, { chave: 'mapeamento', rotulo: 'Mapeamento' }, { chave: 'processando', rotulo: 'Processando' }, { chave: 'resultado', rotulo: 'Resultado' }];

const imp = ref(null);
const passo = computed(() => {
    if (!imp.value) return 'arquivo';
    if (imp.value.status === 'aguardando_mapeamento') return 'mapeamento';
    if (['na_fila', 'processando'].includes(imp.value.status)) return 'processando';
    return 'resultado';
});
function sincronizarUrl() {
    const url = new URL(window.location.href);
    imp.value ? url.searchParams.set('id', imp.value.id) : url.searchParams.delete('id');
    window.history.replaceState(window.history.state, '', url);
}

// ── Passo 1: arquivo ──
const enviando = ref(false);
const erroArquivo = ref('');
const arrastando = ref(false);
async function enviar(arquivo) {
    if (!arquivo) return;
    enviando.value = true;
    erroArquivo.value = '';
    const dados = new FormData();
    dados.append('arquivo', arquivo);
    try {
        const { data } = await axios.post(route('api.importacoes.store'), dados, { silencioso: true });
        imp.value = data.data;
        prepararMapeamento();
        sincronizarUrl();
    } catch (e) {
        erroArquivo.value = errosDeValidacao(e).arquivo?.[0] ?? mensagemDeErro(e, 'Não foi possível ler o arquivo.');
    } finally {
        enviando.value = false;
    }
}
const aoSoltar = (e) => { arrastando.value = false; enviar(e.dataTransfer.files?.[0]); };
function baixarModelo() {
    const linhas = ['nome;email;telefone;descricao;funil;estagio;valor_estimado;probabilidade;previsao_fechamento;responsavel', 'Ana Souza;ana@empresa.com.br;(11) 98888-7777;Indicação;;;1500,00;40;30/11/2026;'];
    const blob = new Blob(['﻿' + linhas.join('\n')], { type: 'text/csv;charset=utf-8' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'modelo-importacao-leads.csv' });
    a.click();
    URL.revokeObjectURL(a.href);
}

// ── Passo 2: mapeamento ──
const mapa = reactive({});
const opcoes = reactive({ duplicados: 'ignorar', funil_id: null, estagio_id: null, user_id: null });
const errosMapa = ref({});
const iniciando = ref(false);
function prepararMapeamento() {
    for (const c of props.campos) mapa[c] = imp.value?.mapeamento?.[c] ?? null;
    opcoes.funil_id = props.funis.find((f) => f.is_default)?.id ?? null;
}
const opcoesColuna = computed(() => (imp.value?.cabecalhos ?? []).map((c, i) => ({ valor: i, rotulo: c || `Coluna ${i + 1}` })));
const estagiosDoFunil = computed(() => props.estagios.filter((e) => e.funil_id === opcoes.funil_id && e.tipo !== 'perdido').map((e) => ({ valor: e.id, rotulo: e.descricao })));
watch(() => opcoes.funil_id, () => { opcoes.estagio_id = null; });
const faltaObrigatorio = computed(() => mapa.nome === null || mapa.email === null);
async function iniciar() {
    const ok = await confirmar({ titulo: `Importar ${imp.value.total_linhas} linha${imp.value.total_linhas === 1 ? '' : 's'}?`, mensagem: opcoes.duplicados === 'atualizar' ? 'Leads com o mesmo e-mail serão atualizados (campos vazios não apagam nada).' : 'Linhas com e-mail que já existe serão puladas.', rotuloConfirmar: `Importar ${imp.value.total_linhas} linhas`, tom: 'primary' });
    if (!ok) return;
    iniciando.value = true;
    errosMapa.value = {};
    try {
        const mapeamento = Object.fromEntries(Object.entries(mapa).filter(([, v]) => v !== null));
        const { data } = await axios.post(route('api.importacoes.iniciar', imp.value.id), { mapeamento, opcoes }, { silencioso: true });
        imp.value = data.data;
        acompanhar();
    } catch (e) {
        errosMapa.value = errosDeValidacao(e);
        if (!Object.keys(errosMapa.value).length) toast.erro(mensagemDeErro(e, 'Não foi possível iniciar.'));
    } finally {
        iniciando.value = false;
    }
}

// ── Passo 3: acompanhar (polling 2 s) ──
let timer = null;
function acompanhar() {
    clearTimeout(timer);
    if (passo.value !== 'processando') return;
    timer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('api.importacoes.show', imp.value.id), { silencioso: true });
            imp.value = data.data;
            if (passo.value === 'resultado' && imp.value.status === 'concluida') {
                toast.sucesso(`Importação concluída: ${imp.value.criadas} criados, ${imp.value.atualizadas} atualizados, ${imp.value.ignoradas} pulados, ${imp.value.com_erro} com erro.`);
            }
        } catch { /* tenta de novo */ }
        acompanhar();
    }, 2000);
}
const progresso = computed(() => (imp.value?.total_linhas ? Math.round(((imp.value.processadas ?? 0) / imp.value.total_linhas) * 100) : 0));

onMounted(async () => {
    const id = new URLSearchParams(window.location.search).get('id');
    if (!id) return;
    try {
        const { data } = await axios.get(route('api.importacoes.show', id), { silencioso: true });
        imp.value = data.data;
        prepararMapeamento();
        acompanhar();
    } catch { sincronizarUrl(); }
});
onBeforeUnmount(() => clearTimeout(timer));

function recomecar() {
    clearTimeout(timer);
    imp.value = null;
    sincronizarUrl();
}
const STATUS = { aguardando_mapeamento: ['Aguardando mapeamento', 'neutral'], na_fila: ['Na fila', 'info'], processando: ['Processando', 'info'], concluida: ['Concluída', 'ok'], falhou: ['Falhou', 'danger'] };
</script>

<template>
    <Head title="Importar leads" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Importar leads" subtitulo="Traga sua planilha em CSV. Você confere o mapeamento antes de importar.">
                <template #migalhas>
                    <Link :href="route('leads.index')" class="hover:text-fg-0">Leads</Link>
                    <ChevronRight :size="14" aria-hidden="true" />
                    <span class="text-fg-1">Importar</span>
                </template>
            </PageHeader>

            <ol class="mb-5 flex flex-wrap items-center gap-2 text-sm" aria-label="Passos">
                <li v-for="(p, i) in PASSOS" :key="p.chave" class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold" :class="p.chave === passo ? 'bg-primary text-primary-fg' : PASSOS.findIndex((x) => x.chave === passo) > i ? 'bg-ok-subtle text-ok' : 'bg-bg-2 text-fg-2'">{{ i + 1 }}</span>
                    <span :class="p.chave === passo ? 'font-medium text-fg-0' : 'text-fg-2'" :aria-current="p.chave === passo ? 'step' : undefined">{{ p.rotulo }}</span>
                    <ChevronRight v-if="i < PASSOS.length - 1" :size="14" class="text-fg-disabled" aria-hidden="true" />
                </li>
            </ol>

            <!-- 1. Arquivo -->
            <template v-if="passo === 'arquivo'">
                <AppCard>
                    <label
                        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-12 text-center transition-colors duration-1 ease-ui"
                        :class="arrastando ? 'border-primary bg-primary-subtle' : 'border-line hover:border-line-2'"
                        @dragover.prevent="arrastando = true"
                        @dragleave="arrastando = false"
                        @drop.prevent="aoSoltar"
                    >
                        <FileUp :size="32" class="text-fg-2" aria-hidden="true" />
                        <span class="text-md font-medium text-fg-0">{{ enviando ? 'Lendo o arquivo…' : 'Arraste o CSV aqui ou clique para escolher' }}</span>
                        <span class="text-sm text-fg-2">Até 10 MB. Separador ; , ou tab e acentos do Excel são reconhecidos.</span>
                        <input type="file" accept=".csv,text/csv" class="sr-only" :disabled="enviando" @change="enviar($event.target.files?.[0])" />
                    </label>
                    <AppAlert v-if="erroArquivo" tom="danger" class="mt-4">{{ erroArquivo }}</AppAlert>
                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-fg-2">
                        <AppButton variante="secondary" tamanho="sm" @click="baixarModelo"><template #icone><Download /></template>Baixar modelo CSV</AppButton>
                        <span>Colunas reconhecidas: {{ campos.map((c) => ROTULOS[c]).join(', ') }}.</span>
                    </div>
                </AppCard>

                <AppCard v-if="importacoes.length" class="mt-4">
                    <SectionHeader titulo="Importações anteriores" />
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="i in importacoes" :key="i.id" class="flex flex-wrap items-center gap-3 py-2 text-sm">
                            <span class="min-w-0 flex-1 truncate font-medium text-fg-0">{{ i.nome_original }}</span>
                            <AppBadge :tom="STATUS[i.status]?.[1] ?? 'neutral'">{{ STATUS[i.status]?.[0] ?? i.status }}</AppBadge>
                            <span class="num text-fg-2">{{ i.criadas }} criados · {{ i.atualizadas }} atualizados · {{ i.com_erro }} com erro</span>
                            <span class="text-fg-2">{{ formatarDataHora(i.created_at) }}</span>
                            <a v-if="i.com_erro || i.avisos" :href="route('api.importacoes.erros', i.id)" class="font-medium text-primary hover:underline">Erros (CSV)</a>
                        </li>
                    </ul>
                </AppCard>
            </template>

            <!-- 2. Mapeamento -->
            <template v-else-if="passo === 'mapeamento'">
                <AppCard>
                    <SectionHeader :titulo="imp.nome_original" :descricao="`${imp.total_linhas} linha(s) · separador “${imp.delimitador === '\t' ? 'tab' : imp.delimitador}” · ${imp.codificacao}`" />
                    <div class="mt-4 grid gap-x-6 gap-y-3 md:grid-cols-2">
                        <FormField v-for="c in campos" :key="c" :rotulo="ROTULOS[c]" :obrigatorio="c === 'nome' || c === 'email'" :erro="errosMapa[`mapeamento.${c}`]">
                            <AppSelect v-model="mapa[c]" :opcoes="opcoesColuna" placeholder="Não importar" tamanho="sm" />
                        </FormField>
                    </div>
                    <AppAlert v-if="faltaObrigatorio" tom="warn" class="mt-4">Escolha as colunas de Nome e E-mail: são obrigatórias.</AppAlert>
                </AppCard>

                <AppCard class="mt-4">
                    <SectionHeader titulo="Prévia" descricao="As primeiras linhas como serão lidas." />
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase tracking-wider text-fg-2"><tr><th v-for="(c, i) in imp.cabecalhos" :key="i" class="whitespace-nowrap px-2 py-2 text-left font-medium">{{ c }}</th></tr></thead>
                            <tbody class="divide-y divide-line"><tr v-for="(l, j) in imp.amostra" :key="j"><td v-for="(v, i) in l" :key="i" class="whitespace-nowrap px-2 py-1.5 text-fg-1">{{ v }}</td></tr></tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard class="mt-4">
                    <SectionHeader titulo="Padrões" descricao="Usados quando a linha não traz o campo ou traz um valor não reconhecido." />
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormField rotulo="Funil">
                            <AppSelect v-model="opcoes.funil_id" :opcoes="funis.map((f) => ({ valor: f.id, rotulo: f.nome }))" />
                        </FormField>
                        <FormField rotulo="Estágio de entrada">
                            <AppSelect v-model="opcoes.estagio_id" :opcoes="estagiosDoFunil" placeholder="Primeiro estágio aberto" />
                        </FormField>
                        <FormField rotulo="Responsável">
                            <AppSelect v-model="opcoes.user_id" :opcoes="agentes.map((a) => ({ valor: a.id, rotulo: a.name }))" placeholder="Eu" />
                        </FormField>
                        <FormField rotulo="E-mail que já existe">
                            <AppSelect v-model="opcoes.duplicados" :opcoes="[{ valor: 'ignorar', rotulo: 'Pular a linha' }, { valor: 'atualizar', rotulo: 'Atualizar o lead existente' }]" />
                        </FormField>
                    </div>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <AppButton variante="secondary" @click="recomecar">Trocar arquivo</AppButton>
                        <AppButton :carregando="iniciando" :disabled="faltaObrigatorio" @click="iniciar"><template #icone><Upload /></template>Importar {{ imp.total_linhas }} linhas</AppButton>
                    </div>
                </AppCard>
            </template>

            <!-- 3. Processando -->
            <AppCard v-else-if="passo === 'processando'">
                <SectionHeader titulo="Importando…" :descricao="`${imp.processadas} de ${imp.total_linhas} linhas`" />
                <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-bg-2" role="progressbar" :aria-valuenow="progresso" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-primary transition-[width] duration-3 ease-ui" :style="{ width: `${progresso}%` }" />
                </div>
                <p class="mt-3 text-sm text-fg-2">Pode sair desta página: a importação continua e o resultado fica em “Importações anteriores”.</p>
            </AppCard>

            <!-- 4. Resultado -->
            <template v-else>
                <AppAlert v-if="imp.status === 'falhou'" tom="danger" titulo="A importação parou">{{ imp.mensagem_falha }}</AppAlert>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4" :class="imp.status === 'falhou' ? 'mt-4' : ''">
                    <KpiCard rotulo="Criados" :valor="imp.criadas" tom="ok" />
                    <KpiCard rotulo="Atualizados" :valor="imp.atualizadas" />
                    <KpiCard rotulo="Pulados" :valor="imp.ignoradas" sub="e-mail já existente" />
                    <KpiCard rotulo="Com erro" :valor="imp.com_erro" :tom="imp.com_erro ? 'danger' : 'neutral'" :sub="imp.avisos ? `+ ${imp.avisos} aviso(s)` : null" />
                </div>
                <AppCard v-if="imp.erros?.length" class="mt-4">
                    <SectionHeader titulo="Linhas com erro ou aviso" descricao="Erro: a linha não entrou. Aviso: entrou com o valor padrão.">
                        <template #acoes><AppButton variante="secondary" tamanho="sm" as="a" :href="route('api.importacoes.erros', imp.id)"><template #icone><Download /></template>Baixar CSV</AppButton></template>
                    </SectionHeader>
                    <ul class="mt-3 divide-y divide-line text-sm">
                        <li v-for="(e, i) in imp.erros" :key="i" class="flex items-start gap-3 py-2">
                            <span class="num w-16 shrink-0 text-fg-2">Linha {{ e.linha }}</span>
                            <AppBadge :tom="e.aviso ? 'warn' : 'danger'">{{ e.aviso ? 'Aviso' : 'Erro' }}</AppBadge>
                            <span class="text-fg-1">{{ e.erro }}</span>
                        </li>
                    </ul>
                </AppCard>
                <p v-else-if="imp.status === 'concluida'" class="mt-4 text-sm text-fg-2">Tudo importado sem erros.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <AppButton as="Link" :href="route('leads.index', { sort: 'created_at', dir: 'desc' })">Ver leads</AppButton>
                    <AppButton variante="secondary" @click="recomecar">Importar outro arquivo</AppButton>
                </div>
            </template>
        </PageContainer>
    </AuthenticatedLayout>
</template>
