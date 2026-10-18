<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { ArrowRightLeft, Check, ChevronDown } from 'lucide-vue-next';
import AppBadge from '@/Components/ui/AppBadge.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppDropdownSeparador from '@/Components/ui/AppDropdownSeparador.vue';
import { usePerda } from '@/Composables/usePerda';
import { useToast } from '@/Composables/useToast';
import { mensagemDeErro } from '@/utils/erros';
import ConfirmarGanhoDialog from './ConfirmarGanhoDialog.vue';
import MoverFunilDialog from './MoverFunilDialog.vue';

/**
 * O badge do estágio no cabeçalho do lead abre o menu de transição: os
 * estágios do funil atual e "Mover para outro funil…". Aplica as três
 * regras no mesmo lugar do Kanban: aberto → direto com Desfazer; perdido →
 * motivo; ganho → confirmação com valor fechado.
 *
 * Emite `atualizado(lead)` com o lead que o backend devolveu.
 */
const props = defineProps({
    lead: { type: Object, required: true }, // { id, nome, estagio_id, funil_id, estagio: { descricao, cor, tipo }, valor_estimado, projetos_abertos_count }
    funis: { type: Array, required: true },
    somenteLeitura: { type: Boolean, default: false },
});

const emit = defineEmits(['atualizado']);

const { pedirPerda } = usePerda();
const toast = useToast();

const funilAtual = computed(() => props.funis.find((f) => f.id === props.lead.funil_id));
const estagios = computed(() => funilAtual.value?.estagios ?? []);
const estagioAtual = computed(() => props.lead.estagio ?? estagios.value.find((e) => e.id === props.lead.estagio_id) ?? null);
const tomDoEstagio = (e) => (e?.tipo === 'ganho' ? 'ok' : e?.tipo === 'perdido' ? 'danger' : null);

const ganho = ref({ aberto: false, destino: null, salvando: false, erro: '' });
const funil = ref({ aberto: false, salvando: false, erro: '' });

const patchEstagio = (payload) => axios.patch(route('api.leads.estagio', props.lead.id), payload);

async function escolher(destino) {
    if (destino.id === props.lead.estagio_id) return;
    const anterior = props.lead.estagio_id;

    if (destino.tipo === 'perdido') {
        const ok = await pedirPerda({
            titulo: `Perder “${props.lead.nome}”`,
            detalhe: props.lead.projetos_abertos_count > 0 ? `Este lead tem ${props.lead.projetos_abertos_count} projeto(s) em aberto. Eles continuam como estão; feche-os na aba Projetos se for o caso.` : null,
            enviar: async (perda) => {
                const { data } = await patchEstagio({ estagio_id: destino.id, perda });
                emit('atualizado', data.data);
            },
        });
        if (ok) toast.sucesso(`Perda registrada para “${props.lead.nome}”.`);
        return;
    }

    if (destino.tipo === 'ganho') {
        ganho.value = { aberto: true, destino, salvando: false, erro: '' };
        return;
    }

    try {
        const { data } = await patchEstagio({ estagio_id: destino.id });
        emit('atualizado', data.data);
        toast.sucesso(`“${props.lead.nome}” movido para “${destino.descricao}”.`, {
            acao: { rotulo: 'Desfazer', onClick: () => patchEstagio({ estagio_id: anterior }).then(({ data }) => emit('atualizado', data.data)) },
        });
    } catch {
        /* o interceptor já avisou */
    }
}

async function confirmarGanho({ valor_fechado }) {
    ganho.value.salvando = true;
    ganho.value.erro = '';
    try {
        const { data } = await patchEstagio({ estagio_id: ganho.value.destino.id, valor_fechado }, { silencioso: true });
        emit('atualizado', data.data);
        ganho.value.aberto = false;
        toast.sucesso(`Negócio ganho: “${props.lead.nome}”.`);
    } catch (e) {
        ganho.value.erro = mensagemDeErro(e, 'Não foi possível marcar como ganho.');
    } finally {
        ganho.value.salvando = false;
    }
}

async function confirmarFunil({ funil_id, estagio_id }) {
    const destinoFunil = props.funis.find((f) => f.id === funil_id);
    const destino = destinoFunil?.estagios.find((e) => e.id === estagio_id);
    const enviar = (perda) => axios.patch(route('api.leads.funil', props.lead.id), { funil_id, estagio_id, perda }, { silencioso: true });

    if (destino?.tipo === 'perdido') {
        funil.value.aberto = false;
        const ok = await pedirPerda({
            titulo: `Perder “${props.lead.nome}”`,
            enviar: async (perda) => {
                const { data } = await enviar(perda);
                emit('atualizado', data.data);
            },
        });
        if (ok) toast.sucesso(`“${props.lead.nome}” movido para “${destinoFunil.nome}” e registrado como perda.`);
        return;
    }

    funil.value.salvando = true;
    funil.value.erro = '';
    try {
        const { data } = await enviar(null);
        emit('atualizado', data.data);
        funil.value.aberto = false;
        toast.sucesso(`“${props.lead.nome}” movido para “${destinoFunil?.nome}” › “${destino?.descricao}”.`);
    } catch (e) {
        funil.value.erro = mensagemDeErro(e, 'Não foi possível mover de funil.');
    } finally {
        funil.value.salvando = false;
    }
}
</script>

<template>
    <AppBadge v-if="somenteLeitura" :cor="tomDoEstagio(estagioAtual) ? null : estagioAtual?.cor" :tom="tomDoEstagio(estagioAtual) ?? 'neutral'" tamanho="md" ponto>
        {{ estagioAtual?.descricao ?? 'Sem estágio' }}
    </AppBadge>
    <AppDropdown v-else rotulo="Mover de estágio" alinhar="start">
        <template #gatilho="{ alternar, atributos }">
            <button type="button" v-bind="atributos" class="inline-flex items-center gap-1 rounded transition-colors duration-1 ease-ui hover:bg-bg-2" aria-label="Mover de estágio" @click="alternar">
                <AppBadge :cor="tomDoEstagio(estagioAtual) ? null : estagioAtual?.cor" :tom="tomDoEstagio(estagioAtual) ?? 'neutral'" tamanho="md" ponto>
                    {{ estagioAtual?.descricao ?? 'Sem estágio' }}
                </AppBadge>
                <ChevronDown :size="14" class="text-fg-2" aria-hidden="true" />
            </button>
        </template>
        <p class="px-2.5 pb-1 pt-1.5 text-xs font-medium uppercase tracking-wider text-fg-2">{{ funilAtual?.nome ?? 'Estágios' }}</p>
        <AppDropdownItem
            v-for="e in estagios"
            :key="e.id"
            :rotulo="e.descricao"
            :icone="e.id === lead.estagio_id ? Check : null"
            :tom="e.tipo === 'perdido' ? 'danger' : 'default'"
            :disabled="e.id === lead.estagio_id"
            @click="escolher(e)"
        />
        <AppDropdownSeparador v-if="funis.length > 1" />
        <AppDropdownItem v-if="funis.length > 1" rotulo="Mover para outro funil…" :icone="ArrowRightLeft" @click="funil = { aberto: true, salvando: false, erro: '' }" />
    </AppDropdown>

    <ConfirmarGanhoDialog
        :aberto="ganho.aberto"
        :nome="lead.nome"
        :valor-estimado="lead.valor_estimado"
        :projetos-abertos="lead.projetos_abertos_count ?? 0"
        :salvando="ganho.salvando"
        :erro="ganho.erro"
        @confirmar="confirmarGanho"
        @cancelar="ganho.aberto = false"
    />
    <MoverFunilDialog
        :aberto="funil.aberto"
        :nome="lead.nome"
        :funis="funis"
        :funil-atual-id="lead.funil_id"
        :salvando="funil.salvando"
        :erro="funil.erro"
        @confirmar="confirmarFunil"
        @cancelar="funil.aberto = false"
    />
</template>
