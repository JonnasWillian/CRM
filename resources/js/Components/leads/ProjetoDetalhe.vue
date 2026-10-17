<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { Download, FileText, Paperclip, Pencil, Trash2, Upload } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import AppPagination from '@/Components/ui/AppPagination.vue';
import AppTabs from '@/Components/ui/AppTabs.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatarDataHora, formatarTamanho } from '@/utils/formatadores';

/** Anotações e anexos de um projeto, dentro do card expandido. */
const props = defineProps({ projeto: { type: Object, required: true } });
const emit = defineEmits(['contagens']);

const toast = useToast();
const { confirmar } = useConfirm();

const aba = ref('anotacoes');
const anotacoes = useListagemAxios(() => route('api.projetos.anotacoes.index', props.projeto.id), { padrao: { per_page: 10 } });
const anexos = useListagemAxios(() => route('api.projetos.anexos.index', props.projeto.id), { padrao: { per_page: 10 } });

const contagens = () => emit('contagens', { anotacoes: anotacoes.meta.value?.total ?? 0, anexos: anexos.meta.value?.total ?? 0 });

// ── Anotações ──
const nova = ref('');
const erroNova = ref(null);
const editando = ref(null);

async function salvarNova() {
    if (!nova.value.trim()) return;
    erroNova.value = null;
    try {
        await axios.post(route('api.projetos.anotacoes.store', props.projeto.id), { descricao: nova.value.trim() }, { silencioso: true });
        nova.value = '';
        toast.sucesso('Anotação salva.');
        await anotacoes.aplicar({});
        contagens();
    } catch (e) {
        erroNova.value = errosDeValidacao(e).descricao?.[0] ?? mensagemDeErro(e);
    }
}

async function salvarEdicao() {
    try {
        await axios.put(route('api.projeto-anotacoes.update', editando.value.id), { descricao: editando.value.descricao.trim() });
        editando.value = null;
        toast.sucesso('Anotação salva.');
        await anotacoes.recarregar();
    } catch { /* interceptor */ }
}

async function apagarAnotacao(a) {
    const ok = await confirmar({ titulo: 'Apagar esta anotação?', mensagem: 'Ela sai do projeto e da timeline. Não dá para desfazer.', rotuloConfirmar: 'Apagar', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.projeto-anotacoes.destroy', a.id));
        toast.sucesso('Anotação apagada.');
        await anotacoes.recarregar();
        contagens();
    } catch { /* interceptor */ }
}

// ── Anexos ──
const enviando = ref(false);

async function enviarAnexos(e) {
    const files = Array.from(e.target.files ?? []);
    e.target.value = null;
    if (!files.length) return;
    enviando.value = true;
    let ok = 0;
    const falhas = [];
    for (const file of files) {
        const fd = new FormData();
        fd.append('arquivo', file);
        fd.append('nome', file.name);
        try {
            await axios.post(route('api.projetos.anexos.store', props.projeto.id), fd, { headers: { 'Content-Type': 'multipart/form-data' }, silencioso: true });
            ok++;
        } catch (err) {
            falhas.push(`${file.name}: ${errosDeValidacao(err).arquivo?.[0] ?? mensagemDeErro(err)}`);
        }
    }
    enviando.value = false;
    if (ok) {
        toast.sucesso(`${ok} anexo${ok === 1 ? '' : 's'} enviado${ok === 1 ? '' : 's'}.`);
        await anexos.recarregar();
        contagens();
    }
    for (const f of falhas) toast.erro(f);
}

async function apagarAnexo(a) {
    const ok = await confirmar({ titulo: `Apagar “${a.nome || 'anexo'}”?`, mensagem: 'O arquivo é removido do servidor. Não dá para desfazer.', rotuloConfirmar: 'Apagar', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.projeto-anexos.destroy', a.id));
        toast.sucesso('Anexo apagado.');
        await anexos.recarregar();
        contagens();
    } catch { /* interceptor */ }
}
</script>

<template>
    <div class="border-t border-line bg-bg-0/50 px-4 pb-4">
        <AppTabs v-model="aba" :abas="[{ chave: 'anotacoes', rotulo: 'Anotações', contagem: anotacoes.meta.value?.total ?? 0 }, { chave: 'anexos', rotulo: 'Anexos', contagem: anexos.meta.value?.total ?? 0 }]" tamanho="sm" />

        <div v-if="aba === 'anotacoes'" class="mt-3 space-y-3">
            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <AppTextarea v-model="nova" :rows="2" limite="anotacao" placeholder="Registre um acontecimento ou observação" @keydown.ctrl.enter.prevent="salvarNova" />
                    <p v-if="erroNova" class="mt-1 text-sm text-danger" role="alert">{{ erroNova }}</p>
                </div>
                <AppButton tamanho="sm" :disabled="!nova.trim()" @click="salvarNova">Salvar</AppButton>
            </div>
            <div v-if="anotacoes.carregando.value && !anotacoes.linhas.value.length"><SkeletonRows :linhas="2" :colunas="2" /></div>
            <EmptyState v-else-if="!anotacoes.linhas.value.length" :icone="FileText" titulo="Nenhuma anotação ainda" tamanho="sm" />
            <ul v-else class="space-y-2">
                <li v-for="a in anotacoes.linhas.value" :key="a.id" class="rounded border border-line bg-bg-1 p-3">
                    <template v-if="editando?.id !== a.id">
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 flex-1 whitespace-pre-wrap text-md text-fg-0">{{ a.descricao }}</p>
                            <div class="flex shrink-0 gap-0.5">
                                <IconButton :icone="Pencil" rotulo="Editar" tamanho="sm" @click="editando = { id: a.id, descricao: a.descricao }" />
                                <IconButton :icone="Trash2" rotulo="Apagar" tamanho="sm" variante="danger" @click="apagarAnotacao(a)" />
                            </div>
                        </div>
                        <p class="num mt-1 text-xs text-fg-2">{{ formatarDataHora(a.created_at) }}</p>
                    </template>
                    <template v-else>
                        <AppTextarea v-model="editando.descricao" :rows="2" limite="anotacao" autofocus />
                        <div class="mt-2 flex justify-end gap-2">
                            <AppButton variante="secondary" tamanho="sm" @click="editando = null">Cancelar</AppButton>
                            <AppButton tamanho="sm" :disabled="!editando.descricao.trim()" @click="salvarEdicao">Salvar</AppButton>
                        </div>
                    </template>
                </li>
            </ul>
            <AppPagination v-if="anotacoes.meta.value && anotacoes.meta.value.last_page > 1" :meta="anotacoes.meta.value" :opcoes-por-pagina="[10, 25, 50]" @update:pagina="anotacoes.irParaPagina" @update:por-pagina="anotacoes.porPagina" />
        </div>

        <div v-else class="mt-3 space-y-3">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded border border-line bg-bg-1 px-3 py-2 text-sm font-medium text-fg-1 hover:bg-bg-2">
                <Upload :size="14" aria-hidden="true" /> {{ enviando ? 'Enviando…' : 'Selecionar arquivos' }}
                <input type="file" multiple class="sr-only" :disabled="enviando" @change="enviarAnexos" />
            </label>
            <div v-if="anexos.carregando.value && !anexos.linhas.value.length"><SkeletonRows :linhas="2" :colunas="2" /></div>
            <EmptyState v-else-if="!anexos.linhas.value.length" :icone="Paperclip" titulo="Nenhum anexo ainda" tamanho="sm" />
            <ul v-else class="divide-y divide-line rounded border border-line bg-bg-1">
                <li v-for="a in anexos.linhas.value" :key="a.id" class="flex items-center justify-between gap-3 px-3 py-2">
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <FileText :size="14" class="shrink-0 text-fg-2" aria-hidden="true" />
                        <span class="truncate font-medium text-fg-0">{{ a.nome || 'Arquivo sem nome' }}</span>
                        <span class="num shrink-0 text-xs text-fg-2">{{ formatarTamanho(a.tamanho) }} · {{ formatarDataHora(a.created_at) }}</span>
                    </div>
                    <div class="flex shrink-0 gap-0.5">
                        <IconButton :icone="Download" rotulo="Baixar" tamanho="sm" as="a" :href="a.url_download" />
                        <IconButton :icone="Trash2" rotulo="Apagar" tamanho="sm" variante="danger" @click="apagarAnexo(a)" />
                    </div>
                </li>
            </ul>
            <AppPagination v-if="anexos.meta.value && anexos.meta.value.last_page > 1" :meta="anexos.meta.value" :opcoes-por-pagina="[10, 25, 50]" @update:pagina="anexos.irParaPagina" @update:por-pagina="anexos.porPagina" />
        </div>
    </div>
</template>
