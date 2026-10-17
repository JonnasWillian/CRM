<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { Download, FileText, Paperclip, Trash2, Upload, X } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatarDataHora, formatarTamanho } from '@/utils/formatadores';

const props = defineProps({ lead: { type: Object, required: true } });
const emit = defineEmits(['contagem']);

const toast = useToast();
const { confirmar } = useConfirm();

const lista = useListagemAxios(() => route('api.leads.arquivos.index', props.lead.id), { padrao: { per_page: 20 } });

const colunas = [
    { key: 'nome', rotulo: 'Documento', primaria: true },
    { key: 'tamanho', rotulo: 'Tamanho', formatar: (v) => formatarTamanho(v), ocultarAbaixo: 'sm', alinhar: 'direita', largura: '120px' },
    { key: 'created_at', rotulo: 'Enviado em', formatar: (v) => formatarDataHora(v), ocultarAbaixo: 'md', largura: '170px' },
];

function emitirContagem() {
    if (lista.meta.value) emit('contagem', { chave: 'arquivos', total: lista.meta.value.total });
}

// ── Pendentes ──
const pendentes = ref([]);
const enviando = ref(false);
const progresso = ref(0);
const arrastando = ref(false);

function adicionar(files) {
    for (const file of Array.from(files ?? [])) {
        if (pendentes.value.some((a) => a.name === file.name && a.size === file.size)) continue;
        pendentes.value.push({ id: `${Date.now()}-${Math.random()}`, file, name: file.name, size: file.size, erro: null });
    }
}

const aoSelecionar = (e) => { adicionar(e.target.files); e.target.value = null; };
const aoSoltar = (e) => { arrastando.value = false; adicionar(e.dataTransfer?.files); };

async function limparPendentes() {
    if (pendentes.value.length >= 3) {
        const ok = await confirmar({ titulo: `Descartar ${pendentes.value.length} arquivos selecionados?`, mensagem: 'Nenhum deles foi enviado ainda.', rotuloConfirmar: 'Descartar', tom: 'danger' });
        if (!ok) return;
    }
    pendentes.value = [];
}

async function enviar() {
    if (!pendentes.value.length) return;
    enviando.value = true;
    progresso.value = 0;
    const total = pendentes.value.length;
    let ok = 0;
    for (const [i, arq] of pendentes.value.entries()) {
        const fd = new FormData();
        fd.append('arquivo', arq.file);
        fd.append('nome', arq.name);
        try {
            await axios.post(route('api.leads.arquivos.store', props.lead.id), fd, { headers: { 'Content-Type': 'multipart/form-data' }, silencioso: true });
            arq.enviado = true;
            ok++;
        } catch (e) {
            arq.erro = errosDeValidacao(e).arquivo?.[0] ?? mensagemDeErro(e, 'Não foi possível enviar.');
        }
        progresso.value = Math.round(((i + 1) / total) * 100);
    }
    pendentes.value = pendentes.value.filter((a) => !a.enviado);
    enviando.value = false;
    if (ok) {
        toast.sucesso(`${ok} arquivo${ok === 1 ? '' : 's'} enviado${ok === 1 ? '' : 's'}.`);
        await lista.recarregar();
        emitirContagem();
    }
    if (pendentes.value.length) toast.erro(`${pendentes.value.length} arquivo(s) não ${pendentes.value.length === 1 ? 'foi' : 'foram'} enviado(s). Veja o motivo ao lado de cada um.`);
}

async function apagar(arquivo) {
    const ok = await confirmar({ titulo: `Apagar “${arquivo.nome || 'arquivo'}”?`, mensagem: 'O arquivo é removido do servidor. Não dá para desfazer.', rotuloConfirmar: 'Apagar', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.arquivos.destroy', arquivo.id));
        toast.sucesso('Documento apagado.');
        await lista.recarregar();
        emitirContagem();
    } catch {
        /* interceptor */
    }
}

const vazio = computed(() => ({ titulo: 'Nenhum documento', descricao: 'Arraste arquivos aqui ou selecione.', icone: Paperclip }));
</script>

<template>
    <div class="space-y-4">
        <div
            class="rounded-lg border border-dashed p-4 transition-colors duration-1 ease-ui"
            :class="arrastando ? 'border-primary bg-primary-subtle' : 'border-line-2 bg-bg-1'"
            @dragover.prevent="arrastando = true"
            @dragleave.prevent="arrastando = false"
            @drop.prevent="aoSoltar"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-sm text-fg-1">
                    <Upload :size="18" class="text-fg-2" aria-hidden="true" />
                    <span>Arraste documentos aqui ou</span>
                    <label class="cursor-pointer font-medium text-primary underline-offset-2 hover:underline">
                        selecione arquivos
                        <input type="file" multiple class="sr-only" @change="aoSelecionar" />
                    </label>
                    <span class="text-fg-2">· até 10 MB cada</span>
                </div>
                <div v-if="pendentes.length" class="flex items-center gap-2">
                    <AppButton variante="ghost" tamanho="sm" :disabled="enviando" @click="limparPendentes">Limpar</AppButton>
                    <AppButton tamanho="sm" :carregando="enviando" @click="enviar">{{ enviando ? `${progresso}%` : `Enviar ${pendentes.length}` }}</AppButton>
                </div>
            </div>
            <ul v-if="pendentes.length" class="mt-3 divide-y divide-line rounded border border-line bg-bg-0/60">
                <li v-for="arq in pendentes" :key="arq.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                    <div class="flex min-w-0 items-center gap-2">
                        <FileText :size="14" class="shrink-0 text-fg-2" aria-hidden="true" />
                        <span class="truncate text-fg-0">{{ arq.name }}</span>
                        <span class="num shrink-0 text-xs text-fg-2">{{ formatarTamanho(arq.size) }}</span>
                        <span v-if="arq.erro" class="truncate text-xs text-danger">{{ arq.erro }}</span>
                    </div>
                    <IconButton :icone="X" rotulo="Remover da lista" tamanho="sm" :disabled="enviando" @click="pendentes = pendentes.filter((a) => a.id !== arq.id)" />
                </li>
            </ul>
            <div v-if="enviando" class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-bg-2"><div class="h-full rounded-full bg-primary transition-[width] duration-2 ease-ui" :style="{ width: `${progresso}%` }" /></div>
        </div>

        <DataTable
            id="lead-documentos"
            :colunas="colunas"
            :linhas="lista.linhas.value"
            :meta="lista.meta.value"
            :carregando="lista.carregando.value"
            :erro="lista.erro.value"
            :linhas-clicaveis="false"
            :vazio="vazio"
            :opcoes-por-pagina="[20, 50, 100]"
            @update:pagina="lista.irParaPagina"
            @update:por-pagina="lista.porPagina"
            @tentar-novamente="lista.recarregar"
        >
            <template #celula-nome="{ linha }">
                <span class="inline-flex items-center gap-2"><FileText :size="15" class="shrink-0 text-fg-2" aria-hidden="true" /><span class="truncate font-medium">{{ linha.nome || 'Arquivo sem nome' }}</span></span>
            </template>
            <template #acoes="{ linha }">
                <IconButton :icone="Download" rotulo="Baixar" as="a" :href="linha.url_download" />
                <IconButton :icone="Trash2" rotulo="Apagar" variante="danger" @click="apagar(linha)" />
            </template>
        </DataTable>
    </div>
</template>
