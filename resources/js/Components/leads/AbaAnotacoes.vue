<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { FileText, Pencil, Trash2 } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import AppCard from '@/Components/ui/AppCard.vue';
import AppPagination from '@/Components/ui/AppPagination.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SkeletonRows from '@/Components/ui/SkeletonRows.vue';
import AppAlert from '@/Components/ui/AppAlert.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useListagemAxios } from '@/Composables/useListagemAxios';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao } from '@/utils/erros';
import { formatarDataHora } from '@/utils/formatadores';

const props = defineProps({ lead: { type: Object, required: true } });
const emit = defineEmits(['contagem']);

const toast = useToast();
const { confirmar } = useConfirm();
const lista = useListagemAxios(() => route('api.leads.anotacoes.index', props.lead.id), { padrao: { per_page: 20 } });

const nova = ref('');
const erroNova = ref(null);
const salvando = ref(false);
const editando = ref(null); // { id, descricao }

const contagem = () => lista.meta.value && emit('contagem', { chave: 'anotacoes', total: lista.meta.value.total });

async function salvarNova() {
    if (!nova.value.trim()) return;
    salvando.value = true;
    erroNova.value = null;
    try {
        await axios.post(route('api.leads.anotacoes.store', props.lead.id), { descricao: nova.value.trim() }, { silencioso: true });
        nova.value = '';
        toast.sucesso('Anotação salva.');
        await lista.aplicar({}, { resetarPagina: true });
        contagem();
    } catch (e) {
        erroNova.value = errosDeValidacao(e).descricao?.[0] ?? 'Não foi possível salvar.';
    } finally {
        salvando.value = false;
    }
}

async function salvarEdicao() {
    if (!editando.value?.descricao.trim()) return;
    try {
        await axios.put(route('api.anotacoes.update', editando.value.id), { descricao: editando.value.descricao.trim() });
        editando.value = null;
        toast.sucesso('Anotação salva.');
        await lista.recarregar();
    } catch {
        /* interceptor */
    }
}

async function apagar(anotacao) {
    const ok = await confirmar({ titulo: 'Apagar esta anotação?', mensagem: 'Ela sai da aba e da timeline. Não dá para desfazer.', rotuloConfirmar: 'Apagar', tom: 'danger' });
    if (!ok) return;
    try {
        await axios.delete(route('api.anotacoes.destroy', anotacao.id));
        toast.sucesso('Anotação apagada.');
        await lista.recarregar();
        contagem();
    } catch {
        /* interceptor */
    }
}
</script>

<template>
    <div class="space-y-4">
        <AppCard padding="sm">
            <FormField rotulo="Nova anotação" :erro="erroNova" dica="Ctrl+Enter salva.">
                <AppTextarea v-model="nova" :rows="3" limite="anotacao" mostrar-contador placeholder="Registre o que foi conversado" @keydown.ctrl.enter.prevent="salvarNova" @keydown.meta.enter.prevent="salvarNova" />
            </FormField>
            <div class="mt-2 flex justify-end"><AppButton tamanho="sm" :disabled="!nova.trim()" :carregando="salvando" @click="salvarNova">Salvar anotação</AppButton></div>
        </AppCard>

        <AppAlert v-if="lista.erro.value" tom="danger" titulo="Não deu para carregar as anotações">
            {{ lista.erro.value }}
            <template #acao><AppButton variante="secondary" tamanho="sm" @click="lista.recarregar">Tentar de novo</AppButton></template>
        </AppAlert>
        <div v-else-if="lista.carregando.value && !lista.linhas.value.length" class="rounded-lg border border-line bg-bg-1"><SkeletonRows :linhas="4" :colunas="2" /></div>
        <EmptyState v-else-if="!lista.linhas.value.length" :icone="FileText" titulo="Nenhuma anotação" descricao="Registre o que foi conversado." tamanho="sm" />
        <ul v-else class="space-y-2" :class="lista.carregando.value ? 'opacity-60' : ''">
            <li v-for="a in lista.linhas.value" :key="a.id" class="rounded-lg border border-line bg-bg-1 p-4 shadow-1">
                <template v-if="editando?.id !== a.id">
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 flex-1 whitespace-pre-wrap text-md text-fg-0">{{ a.descricao }}</p>
                        <div class="flex shrink-0 items-center gap-0.5">
                            <IconButton :icone="Pencil" rotulo="Editar" tamanho="sm" @click="editando = { id: a.id, descricao: a.descricao }" />
                            <IconButton :icone="Trash2" rotulo="Apagar" tamanho="sm" variante="danger" @click="apagar(a)" />
                        </div>
                    </div>
                    <p class="num mt-2 text-xs text-fg-2">{{ formatarDataHora(a.created_at) }}<span v-if="a.updated_at !== a.created_at"> · editada {{ formatarDataHora(a.updated_at) }}</span></p>
                </template>
                <template v-else>
                    <AppTextarea v-model="editando.descricao" :rows="3" limite="anotacao" mostrar-contador autofocus @keydown.ctrl.enter.prevent="salvarEdicao" />
                    <div class="mt-2 flex justify-end gap-2">
                        <AppButton variante="secondary" tamanho="sm" @click="editando = null">Cancelar</AppButton>
                        <AppButton tamanho="sm" :disabled="!editando.descricao.trim()" @click="salvarEdicao">Salvar</AppButton>
                    </div>
                </template>
            </li>
        </ul>
        <AppPagination v-if="lista.meta.value && lista.meta.value.last_page > 1" :meta="lista.meta.value" :opcoes-por-pagina="[20, 50, 100]" :carregando="lista.carregando.value" @update:pagina="lista.irParaPagina" @update:por-pagina="lista.porPagina" />
    </div>
</template>
