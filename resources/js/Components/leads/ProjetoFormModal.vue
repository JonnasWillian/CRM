<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDateInput from '@/Components/ui/AppDateInput.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppSwitch from '@/Components/ui/AppSwitch.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import { usePerda } from '@/Composables/usePerda';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao } from '@/utils/erros';

/**
 * Cria ou edita um projeto do lead. Status com `is_lost` passa pelo modal
 * de motivo (só na ENTRADA em perda: editar o preço de um projeto que já
 * estava perdido não é uma perda nova).
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    leadId: { type: Number, required: true },
    status: { type: Array, required: true },
    projeto: { type: Object, default: null }, // null = criar
});

const emit = defineEmits(['fechar', 'salvo']);

const toast = useToast();
const { pedirPerda } = usePerda();

const vazio = () => ({ nome: '', descricao: '', status_id: null, preco: null, data_inicial: null, data_final: null, parcelas: false, qtd_parcelas: null });
const form = reactive(vazio());
const erros = ref({});
const salvando = ref(false);

watch(
    () => props.aberto,
    (aberto) => {
        if (!aberto) return;
        erros.value = {};
        const p = props.projeto;
        Object.assign(form, p ? {
            nome: p.nome ?? '',
            descricao: p.descricao ?? '',
            status_id: p.status_id ?? null,
            preco: p.preco !== null && p.preco !== undefined ? Number(p.preco) : null,
            data_inicial: p.data_inicial ? String(p.data_inicial).slice(0, 10) : null,
            data_final: p.data_final ? String(p.data_final).slice(0, 10) : null,
            parcelas: Boolean(p.parcelas),
            qtd_parcelas: p.qtd_parcelas ?? null,
        } : { ...vazio(), status_id: props.status.find((s) => !s.is_won && !s.is_lost)?.id ?? props.status[0]?.id ?? null });
    },
    { immediate: true },
);

watch(() => form.parcelas, (v) => { if (!v) form.qtd_parcelas = null; });

const opcoesStatus = computed(() => props.status.map((s) => ({ valor: s.id, rotulo: s.descricao })));
const statusEhPerdido = (id) => Boolean(props.status.find((s) => s.id === id)?.is_lost);

async function salvar() {
    erros.value = {};
    const payload = {
        ...form,
        qtd_parcelas: form.parcelas ? (Number(form.qtd_parcelas) || null) : null,
    };
    const enviar = (perda) => props.projeto
        ? axios.put(route('api.projetos.update', props.projeto.id), { ...payload, perda }, { silencioso: true })
        : axios.post(route('api.leads.projetos.store', props.leadId), { ...payload, perda }, { silencioso: true });

    const entraEmPerda = statusEhPerdido(form.status_id) && !statusEhPerdido(props.projeto?.status_id);

    if (entraEmPerda) {
        emit('fechar');
        const ok = await pedirPerda({
            titulo: props.projeto ? `Perder “${form.nome}”` : `Cadastrar “${form.nome}” como perdido`,
            enviar: async (perda) => {
                await enviar(perda);
            },
        });
        if (ok) {
            toast.sucesso('Perda registrada.');
            emit('salvo');
        }
        return;
    }

    salvando.value = true;
    try {
        await enviar(null);
        toast.sucesso('Projeto salvo.');
        emit('salvo');
        emit('fechar');
    } catch (e) {
        erros.value = errosDeValidacao(e);
        if (!Object.keys(erros.value).length) toast.erro('Não foi possível salvar o projeto.');
    } finally {
        salvando.value = false;
    }
}
</script>

<template>
    <AppModal :aberto="aberto" :titulo="projeto ? 'Editar projeto' : 'Novo projeto'" tamanho="lg" @fechar="emit('fechar')">
        <form id="form-projeto" class="grid gap-4 md:grid-cols-2" @submit.prevent="salvar">
            <FormField rotulo="Nome do projeto" obrigatorio :erro="erros.nome">
                <AppInput v-model="form.nome" limite="nome" placeholder="Ex.: Site institucional" autofocus />
            </FormField>
            <FormField rotulo="Status" obrigatorio :erro="erros.status_id">
                <AppSelect v-model="form.status_id" :opcoes="opcoesStatus" placeholder="Selecione" />
            </FormField>
            <FormField rotulo="Descrição" :erro="erros.descricao" class="md:col-span-2">
                <AppTextarea v-model="form.descricao" :rows="2" limite="descricao" placeholder="Descreva brevemente o projeto" />
            </FormField>
            <FormField rotulo="Valor" :erro="erros.preco">
                <AppMoneyInput v-model="form.preco" />
            </FormField>
            <div class="grid grid-cols-2 gap-4">
                <FormField rotulo="Início" :erro="erros.data_inicial"><AppDateInput v-model="form.data_inicial" /></FormField>
                <FormField rotulo="Fim" :erro="erros.data_final"><AppDateInput v-model="form.data_final" :min="form.data_inicial ?? undefined" /></FormField>
            </div>
            <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                <AppSwitch v-model="form.parcelas" rotulo="Parcelado" />
                <FormField v-if="form.parcelas" rotulo="Nº de parcelas" :erro="erros.qtd_parcelas" inline>
                    <AppInput v-model="form.qtd_parcelas" type="number" min="2" max="120" tamanho="sm" class="w-24" />
                </FormField>
            </div>
        </form>
        <template #rodape>
            <AppButton variante="secondary" @click="emit('fechar')">Cancelar</AppButton>
            <AppButton type="submit" form="form-projeto" :carregando="salvando" :disabled="!form.nome.trim() || !form.status_id">{{ projeto ? 'Salvar' : 'Criar projeto' }}</AppButton>
        </template>
    </AppModal>
</template>
