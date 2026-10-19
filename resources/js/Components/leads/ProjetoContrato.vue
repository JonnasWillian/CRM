<script setup>
import { computed, reactive } from 'vue';
import axios from 'axios';
import { CalendarPlus, RefreshCw, Trash2 } from 'lucide-vue-next';
import AppAlert from '@/Components/ui/AppAlert.vue';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppButton from '@/Components/ui/AppButton.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppMoneyInput from '@/Components/ui/AppMoneyInput.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao, mensagemDeErro } from '@/utils/erros';
import { formatBRL, formatarData } from '@/utils/formatadores';
import { fimDoContrato, somarMeses } from '@/utils/recorrencia';

/**
 * Contrato recorrente: quanto já foi contabilizado (meses iniciados até hoje,
 * incluindo o atual), quanto falta, o valor deste mês, o fim e o histórico
 * de períodos (contrato inicial + renovações e aditivos). Valores calculados
 * pelo servidor a partir do início, do valor mensal e do mês atual.
 */
const props = defineProps({ projeto: { type: Object, required: true } });
const emit = defineEmits(['atualizado']);

const toast = useToast();
const { confirmar } = useConfirm();

const r = computed(() => props.projeto.recorrencia);
const contabiliza = computed(() => Boolean(props.projeto.status?.is_won));
const SITUACAO = {
    a_iniciar: ['A iniciar', 'info'],
    vigente: ['Vigente', 'ok'],
    encerrado: ['Encerrado', 'neutral'],
    cancelado: ['Cancelado', 'danger'],
};
const progresso = computed(() => (r.value?.meses_total ? Math.round((r.value.meses_decorridos / r.value.meses_total) * 100) : 0));

// Histórico: o período 0 é o contrato inicial; os seguintes, os aditivos em ordem.
const historico = computed(() => (r.value?.periodos ?? []).map((p, i) => {
    const aditivo = i === 0 ? null : props.projeto.aditivos?.[i - 1];
    return {
        ...p,
        rotulo: i === 0 ? 'Contrato inicial' : aditivo?.tipo === 'renovacao' ? 'Renovação' : 'Aditivo',
        observacao: aditivo?.observacao ?? null,
        aditivoId: aditivo?.id ?? null,
        ultimo: i > 0 && i === (r.value.periodos.length - 1),
    };
}));

// ── Renovar / aditivo ──
const modal = reactive({ aberto: false, tipo: 'renovacao', meses: 12, valor_mensal: null, observacao: '', erros: {}, salvando: false });
function abrir(tipo) {
    Object.assign(modal, { aberto: true, tipo, meses: tipo === 'renovacao' ? props.projeto.duracao_meses || 12 : 3, valor_mensal: null, observacao: '', erros: {}, salvando: false });
}
const valorAtual = computed(() => r.value?.periodos?.at(-1)?.valor_mensal ?? 0);
const previa = computed(() => {
    const meses = Number(modal.meses);
    if (!r.value || !(meses > 0)) return null;
    const novoFim = fimDoContrato(r.value.inicio, r.value.meses_total + meses);
    const valor = modal.valor_mensal ?? valorAtual.value;
    const inicioDoPeriodo = somarMeses(r.value.inicio, r.value.meses_total);
    return `De ${formatarData(inicioDoPeriodo)} a ${formatarData(novoFim)} · ${meses} × ${formatBRL(valor)} = ${formatBRL(meses * valor)}`;
});
async function salvar() {
    modal.salvando = true;
    modal.erros = {};
    try {
        const { data } = await axios.post(route('api.projetos.aditivos.store', props.projeto.id), {
            tipo: modal.tipo, meses: Number(modal.meses), valor_mensal: modal.valor_mensal, observacao: modal.observacao || null,
        }, { silencioso: true });
        toast.sucesso(data.message);
        modal.aberto = false;
        emit('atualizado', data.data);
    } catch (e) {
        modal.erros = errosDeValidacao(e);
        if (!Object.keys(modal.erros).length) toast.erro(mensagemDeErro(e, 'Não foi possível salvar.'));
    } finally {
        modal.salvando = false;
    }
}

async function remover(linha) {
    const ok = await confirmar({ titulo: `Remover este ${linha.rotulo.toLowerCase()}?`, mensagem: `O contrato volta a terminar em ${formatarData(historico.value.at(-2)?.fim)} e o valor total é recalculado.`, rotuloConfirmar: 'Remover', tom: 'danger' });
    if (!ok) return;
    try {
        const { data } = await axios.delete(route('api.projeto-aditivos.destroy', linha.aditivoId));
        toast.sucesso(data.message);
        emit('atualizado', data.data);
    } catch { /* interceptor mostra a recusa */ }
}
</script>

<template>
    <div v-if="r" class="mt-3 space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <AppBadge :tom="SITUACAO[r.situacao]?.[1] ?? 'neutral'" ponto>{{ SITUACAO[r.situacao]?.[0] ?? r.situacao }}</AppBadge>
            <span class="num text-sm text-fg-1">{{ formatarData(r.inicio) }} → {{ formatarData(r.fim) }}</span>
            <AppBadge v-if="r.vence_em_breve" tom="warn">Vence em breve</AppBadge>
            <div class="ml-auto flex flex-wrap gap-2">
                <AppButton variante="secondary" tamanho="sm" :disabled="r.situacao === 'cancelado'" @click="abrir('renovacao')"><template #icone><RefreshCw /></template>Renovar</AppButton>
                <AppButton variante="secondary" tamanho="sm" :disabled="r.situacao === 'cancelado'" @click="abrir('aditivo')"><template #icone><CalendarPlus /></template>Aditivo</AppButton>
            </div>
        </div>

        <AppAlert v-if="!contabiliza && r.situacao !== 'cancelado'" tom="info">Proposta ainda não ganha: os valores abaixo são previstos e não entram na receita recorrente.</AppAlert>

        <div>
            <div class="mb-1 flex justify-between text-sm"><span class="text-fg-1">{{ r.meses_decorridos }} de {{ r.meses_total }} meses</span><span class="num text-fg-2">{{ progresso }}%</span></div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-bg-2" role="progressbar" :aria-valuenow="progresso" aria-valuemin="0" aria-valuemax="100" :aria-label="`${r.meses_decorridos} de ${r.meses_total} meses`">
                <div class="h-full rounded-full transition-[width] duration-3 ease-ui" :class="r.situacao === 'cancelado' ? 'bg-danger' : 'bg-primary'" :style="{ width: `${progresso}%` }" />
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded border border-line bg-bg-1 p-3"><dt class="text-xs text-fg-2">Valor deste mês</dt><dd class="num mt-1 text-lg font-semibold text-fg-0">{{ formatBRL(r.valor_mes_atual) }}</dd></div>
            <div class="rounded border border-line bg-bg-1 p-3"><dt class="text-xs text-fg-2">{{ contabiliza ? 'Contabilizado até hoje' : 'Previsto até hoje' }}</dt><dd class="num mt-1 text-lg font-semibold text-fg-0">{{ formatBRL(r.valor_realizado) }}</dd></div>
            <div class="rounded border border-line bg-bg-1 p-3"><dt class="text-xs text-fg-2">A realizar</dt><dd class="num mt-1 text-lg font-semibold text-fg-0">{{ formatBRL(r.valor_a_realizar) }}</dd></div>
            <div class="rounded border border-line bg-bg-1 p-3"><dt class="text-xs text-fg-2">Total do contrato</dt><dd class="num mt-1 text-lg font-semibold text-fg-0">{{ formatBRL(r.valor_total) }}</dd></div>
        </dl>

        <div class="max-w-full overflow-x-auto rounded border border-line bg-bg-1">
            <table class="w-full text-sm">
                <thead class="bg-bg-0 text-xs uppercase tracking-wider text-fg-2">
                    <tr><th class="px-3 py-2 text-left font-medium">Período</th><th class="hidden px-3 py-2 text-left font-medium sm:table-cell">Vigência</th><th class="px-3 py-2 text-right font-medium">Meses</th><th class="px-3 py-2 text-right font-medium">Valor/mês</th><th class="hidden px-3 py-2 text-right font-medium sm:table-cell">Subtotal</th><th class="w-px px-2"><span class="sr-only">Ações</span></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="(p, i) in historico" :key="i">
                        <td class="px-3 py-2 text-fg-0">{{ p.rotulo }}<p class="num text-xs text-fg-2 sm:hidden">{{ formatarData(p.inicio) }} → {{ formatarData(p.fim) }}</p><p v-if="p.observacao" class="text-xs text-fg-2">{{ p.observacao }}</p></td>
                        <td class="num hidden whitespace-nowrap px-3 py-2 text-fg-1 sm:table-cell">{{ formatarData(p.inicio) }} → {{ formatarData(p.fim) }}</td>
                        <td class="num px-3 py-2 text-right">{{ p.meses }}</td>
                        <td class="num whitespace-nowrap px-3 py-2 text-right">{{ formatBRL(p.valor_mensal) }}</td>
                        <td class="num hidden whitespace-nowrap px-3 py-2 text-right sm:table-cell">{{ formatBRL(p.meses * p.valor_mensal) }}</td>
                        <td class="px-2"><IconButton v-if="p.ultimo" :icone="Trash2" :rotulo="`Remover ${p.rotulo.toLowerCase()}`" tamanho="sm" variante="danger" @click="remover(p)" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal :aberto="modal.aberto" :titulo="modal.tipo === 'renovacao' ? 'Renovar contrato' : 'Aditivo de prazo'" :descricao="modal.tipo === 'renovacao' ? 'Um novo período começa logo depois do fim atual.' : 'Estende o prazo atual; o valor pode mudar a partir daí.'" tamanho="sm" @fechar="modal.aberto = false">
            <form id="form-aditivo" class="space-y-4" @submit.prevent="salvar">
                <FormField rotulo="Meses a acrescentar" obrigatorio :erro="modal.erros.meses">
                    <AppInput v-model="modal.meses" type="number" :min="1" :max="240" inputmode="numeric" autofocus />
                </FormField>
                <FormField rotulo="Novo valor mensal" :erro="modal.erros.valor_mensal" :dica="`Em branco mantém ${formatBRL(valorAtual)}.`">
                    <AppMoneyInput v-model="modal.valor_mensal" />
                </FormField>
                <FormField rotulo="Observação" :erro="modal.erros.observacao">
                    <AppTextarea v-model="modal.observacao" :rows="2" :maxlength="500" placeholder="Ex.: reajuste anual de 8%" />
                </FormField>
                <p v-if="previa" class="num rounded bg-bg-2 px-3 py-2 text-sm text-fg-1">{{ previa }}</p>
            </form>
            <template #rodape>
                <AppButton variante="secondary" @click="modal.aberto = false">Cancelar</AppButton>
                <AppButton type="submit" form="form-aditivo" :carregando="modal.salvando" :disabled="!(Number(modal.meses) > 0)">{{ modal.tipo === 'renovacao' ? 'Renovar' : 'Adicionar' }}</AppButton>
            </template>
        </AppModal>
    </div>
</template>
