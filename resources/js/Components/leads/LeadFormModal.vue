<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDateInput from '@/Components/ui/AppDateInput.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import AppSelect from '@/Components/ui/AppSelect.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { usePerda } from '@/Composables/usePerda';
import { ehTelefoneBrasileiro, MASCARA_TELEFONE } from '@/utils/telefone';

/**
 * Cadastro completo de lead (grade e dashboard). Posta na rota web
 * `leads.store` com useForm: erros voltam inline, sucesso vira flash/toast e
 * a grade recarrega sozinha. "Salvar e abrir" manda `abrir=1` e o servidor
 * redireciona para o perfil.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    funis: { type: Array, required: true }, // [{ id, nome, is_default }]
    estagios: { type: Array, required: true }, // [{ id, funil_id, descricao, tipo, ordem, probabilidade_padrao }]
    agentes: { type: Array, default: () => [] }, // [{ id, name }] só para leads.view-all
});
const emit = defineEmits(['fechar', 'salvo']);

const { confirmar } = useConfirm();
const { pedirPerda } = usePerda();
const eu = computed(() => usePage().props.auth.user);

const form = useForm({
    nome: '', email: '', telefone: '', descricao: '',
    funil_id: null, estagio_id: null, valor_estimado: null, probabilidade: null, previsao_fechamento: null,
    user_id: null, abrir: false, perda: null,
});

const funilPadrao = () => props.funis.find((f) => f.is_default) ?? props.funis[0] ?? null;
const estagiosDoFunil = computed(() => props.estagios.filter((e) => e.funil_id === form.funil_id));
const estagioAtual = computed(() => props.estagios.find((e) => e.id === form.estagio_id) ?? null);
const primeiroAberto = (funilId) => props.estagios.find((e) => e.funil_id === funilId && e.tipo === 'aberto') ?? props.estagios.find((e) => e.funil_id === funilId) ?? null;

const opcoesFunil = computed(() => props.funis.map((f) => ({ valor: f.id, rotulo: f.nome })));
const opcoesEstagio = computed(() => estagiosDoFunil.value.map((e) => ({ valor: e.id, rotulo: e.descricao })));
const opcoesAgente = computed(() => props.agentes.map((a) => ({ valor: a.id, rotulo: a.id === eu.value?.id ? `${a.name} (eu)` : a.name })));

watch(() => props.aberto, (aberto) => {
    if (!aberto) return;
    form.reset();
    form.clearErrors();
    const funil = funilPadrao();
    form.funil_id = funil?.id ?? null;
    form.estagio_id = funil ? (primeiroAberto(funil.id)?.id ?? null) : null;
    form.user_id = props.agentes.length ? eu.value?.id ?? null : null;
    sugerirProbabilidade();
});

watch(() => form.funil_id, (funilId, anterior) => {
    if (anterior === undefined || funilId === anterior) return;
    form.estagio_id = funilId ? (primeiroAberto(funilId)?.id ?? null) : null;
});

// A probabilidade padrão do estágio pré-preenche só quando o campo está vazio.
function sugerirProbabilidade() {
    const padrao = estagioAtual.value?.probabilidade_padrao;
    if ((form.probabilidade === null || form.probabilidade === '') && padrao !== null && padrao !== undefined) form.probabilidade = padrao;
}
watch(() => form.estagio_id, sugerirProbabilidade);

const preenchido = computed(() => Boolean(form.nome || form.email || form.telefone || form.descricao || form.valor_estimado));

async function fechar() {
    if (form.processing) return;
    if (preenchido.value) {
        const ok = await confirmar({ titulo: 'Descartar o cadastro?', mensagem: 'O que você preencheu será perdido.', rotuloConfirmar: 'Descartar', rotuloCancelar: 'Continuar editando', tom: 'danger' });
        if (!ok) return;
    }
    emit('fechar');
}

function postar(perda = null) {
    return new Promise((resolve, reject) => {
        form.transform((d) => ({ ...d, perda, probabilidade: d.probabilidade === '' ? null : d.probabilidade })).post(route('leads.store'), {
            preserveScroll: true,
            onSuccess: () => { emit('salvo'); emit('fechar'); resolve(); },
            onError: (errors) => reject({ response: { status: 422, data: { message: Object.values(errors)[0], errors } } }),
        });
    });
}

async function salvar(abrir = false) {
    form.abrir = abrir;
    if (estagioAtual.value?.tipo === 'perdido') {
        await pedirPerda({ titulo: `Cadastrar “${form.nome || 'lead'}” já como perdido`, enviar: (perda) => postar(perda) });
        return;
    }
    postar().catch(() => { /* erros já estão em form.errors */ });
}
</script>

<template>
    <AppModal :aberto="aberto" titulo="Novo lead" tamanho="lg" :persistente="form.processing" @fechar="fechar">
        <form id="form-lead" class="grid gap-x-5 gap-y-4 md:grid-cols-2" @submit.prevent="salvar(false)">
            <section class="space-y-4">
                <h3 class="text-xs font-medium uppercase tracking-wider text-fg-2">Contato</h3>
                <FormField rotulo="Nome" obrigatorio :erro="form.errors.nome">
                    <AppInput v-model="form.nome" limite="nome" autofocus autocomplete="off" placeholder="Nome da pessoa ou empresa" />
                </FormField>
                <FormField rotulo="E-mail" obrigatorio :erro="form.errors.email">
                    <AppInput v-model="form.email" type="email" limite="email" autocomplete="off" inputmode="email" />
                </FormField>
                <FormField rotulo="Telefone" :erro="form.errors.telefone" dica="Número internacional: comece com +.">
                    <AppInput v-model="form.telefone" :mascara="ehTelefoneBrasileiro(form.telefone) ? MASCARA_TELEFONE : null" inputmode="tel" placeholder="(00) 00000-0000" />
                </FormField>
                <FormField rotulo="Descrição" :erro="form.errors.descricao">
                    <AppTextarea v-model="form.descricao" :rows="3" limite="descricao" placeholder="Contexto, origem, interesse…" />
                </FormField>
            </section>
            <section class="space-y-4">
                <h3 class="text-xs font-medium uppercase tracking-wider text-fg-2">Negócio</h3>
                <div class="grid grid-cols-2 gap-3">
                    <FormField rotulo="Funil" :erro="form.errors.funil_id">
                        <AppSelect v-model="form.funil_id" :opcoes="opcoesFunil" :disabled="funis.length <= 1" />
                    </FormField>
                    <FormField rotulo="Estágio" :erro="form.errors.estagio_id">
                        <AppSelect v-model="form.estagio_id" :opcoes="opcoesEstagio" placeholder="—" />
                    </FormField>
                </div>
                <FormField rotulo="Valor estimado" :erro="form.errors.valor_estimado">
                    <AppMoneyInput v-model="form.valor_estimado" />
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField rotulo="Probabilidade (%)" :erro="form.errors.probabilidade" :dica="estagioAtual?.probabilidade_padrao !== null && estagioAtual?.probabilidade_padrao !== undefined ? `Sugestão do estágio: ${estagioAtual.probabilidade_padrao}%` : null">
                        <AppInput v-model="form.probabilidade" type="number" :min="0" :max="100" :step="1" inputmode="numeric" />
                    </FormField>
                    <FormField rotulo="Previsão de fechamento" :erro="form.errors.previsao_fechamento">
                        <AppDateInput v-model="form.previsao_fechamento" />
                    </FormField>
                </div>
                <FormField v-if="agentes.length" rotulo="Responsável" :erro="form.errors.user_id">
                    <AppSelect v-model="form.user_id" :opcoes="opcoesAgente" />
                </FormField>
            </section>
        </form>
        <template #rodape>
            <AppButton variante="secondary" :disabled="form.processing" @click="fechar">Cancelar</AppButton>
            <AppButton variante="secondary" :carregando="form.processing && form.abrir" :disabled="form.processing && !form.abrir" @click="salvar(true)">Salvar e abrir</AppButton>
            <AppButton type="submit" form="form-lead" :carregando="form.processing && !form.abrir" :disabled="form.processing && form.abrir">Salvar</AppButton>
        </template>
    </AppModal>
</template>
