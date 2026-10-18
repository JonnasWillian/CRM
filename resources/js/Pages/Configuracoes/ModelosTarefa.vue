<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { LayoutTemplate, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageContainer from '@/Components/ui/PageContainer.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';

/** Modelos de tarefa do próprio agente: título, prazo em dias e anotação, aplicáveis no perfil do lead. */
const props = defineProps({ modelos: { type: Array, required: true } });

const toast = useToast();
const { confirmar } = useConfirm();
const recarregar = () => router.reload({ only: ['modelos'] });

const colunas = [
    { key: 'titulo', rotulo: 'Modelo', primaria: true },
    { key: 'prazo_dias', rotulo: 'Prazo', formatar: (v) => (Number(v) === 0 ? 'No mesmo dia' : `+${v} dia${Number(v) === 1 ? '' : 's'}`) },
    { key: 'anotacao', rotulo: 'Anotação', ocultarAbaixo: 'md', formatar: (v) => v || '—' },
];

const modal = reactive({ aberto: false, modelo: null, titulo: '', prazo_dias: 1, anotacao: '', erros: {}, salvando: false });
function abrir(m = null) { Object.assign(modal, { aberto: true, modelo: m, titulo: m?.titulo ?? '', prazo_dias: m?.prazo_dias ?? 1, anotacao: m?.anotacao ?? '', erros: {}, salvando: false }); }
async function salvar() {
    modal.salvando = true;
    modal.erros = {};
    const payload = { titulo: modal.titulo, prazo_dias: Number(modal.prazo_dias), anotacao: modal.anotacao || null };
    try {
        if (modal.modelo) await axios.put(route('api.tarefa-padroes.update', modal.modelo.id), payload, { silencioso: true });
        else await axios.post(route('api.tarefa-padroes.store'), payload, { silencioso: true });
        toast.sucesso(modal.modelo ? 'Modelo salvo.' : 'Modelo criado.');
        modal.aberto = false;
        recarregar();
    } catch (e) {
        modal.erros = errosDeValidacao(e);
        if (!Object.keys(modal.erros).length) toast.erro(mensagemDeErro(e, 'Não foi possível salvar o modelo.'));
    } finally {
        modal.salvando = false;
    }
}
async function excluir(m) {
    const ok = await confirmar({ titulo: `Apagar o modelo “${m.titulo}”?`, mensagem: 'Tarefas já criadas a partir dele continuam. Não dá para desfazer.', rotuloConfirmar: 'Apagar', tom: 'danger' });
    if (!ok) return;
    try { await axios.delete(route('api.tarefa-padroes.destroy', m.id)); toast.sucesso('Modelo apagado.'); recarregar(); } catch { /* interceptor */ }
}
</script>

<template>
    <Head title="Modelos de tarefa" />
    <AuthenticatedLayout>
        <PageContainer>
            <PageHeader titulo="Modelos de tarefa" subtitulo="Follow-ups prontos para aplicar em dois cliques no perfil de um lead. Os modelos são seus.">
                <template #acoes>
                    <AppButton @click="abrir()"><template #icone><Plus /></template>Novo modelo</AppButton>
                </template>
            </PageHeader>

            <DataTable id="modelos-tarefa" :colunas="colunas" :linhas="modelos" :meta="null" :linhas-clicaveis="false" :cabecalho-fixo="false" :vazio="{ titulo: 'Nenhum modelo', descricao: 'Crie um para aplicar follow-ups em dois cliques.', icone: LayoutTemplate }">
                <template #acoes="{ linha }">
                    <IconButton :icone="Pencil" rotulo="Editar" tamanho="sm" @click="abrir(linha)" />
                    <IconButton :icone="Trash2" rotulo="Apagar" tamanho="sm" variante="danger" @click="excluir(linha)" />
                </template>
                <template #vazio><AppButton @click="abrir()"><template #icone><Plus /></template>Novo modelo</AppButton></template>
            </DataTable>
        </PageContainer>

        <AppModal :aberto="modal.aberto" :titulo="modal.modelo ? 'Editar modelo' : 'Novo modelo de tarefa'" tamanho="sm" @fechar="modal.aberto = false">
            <form id="form-modelo" class="space-y-4" @submit.prevent="salvar">
                <FormField rotulo="Título" obrigatorio :erro="modal.erros.titulo">
                    <AppInput v-model="modal.titulo" :maxlength="255" placeholder="Ex.: Ligar para confirmar recebimento da proposta" autofocus />
                </FormField>
                <FormField rotulo="Prazo (dias após aplicar)" obrigatorio :erro="modal.erros.prazo_dias" dica="0 = no mesmo dia.">
                    <AppInput v-model="modal.prazo_dias" type="number" :min="0" :max="365" inputmode="numeric" />
                </FormField>
                <FormField rotulo="Anotação" :erro="modal.erros.anotacao">
                    <AppTextarea v-model="modal.anotacao" :rows="3" placeholder="Opcional. Vai junto na tarefa criada." />
                </FormField>
            </form>
            <template #rodape>
                <AppButton variante="secondary" @click="modal.aberto = false">Cancelar</AppButton>
                <AppButton type="submit" form="form-modelo" :carregando="modal.salvando" :disabled="!modal.titulo.trim()">{{ modal.modelo ? 'Salvar' : 'Criar modelo' }}</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
