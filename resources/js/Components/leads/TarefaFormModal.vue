<script setup>
import { reactive, ref, watch } from 'vue';
import axios from 'axios';
import { ChevronDown, LayoutTemplate } from 'lucide-vue-next';
import AppButton from '@/Components/ui/AppButton.vue';
import AppDateInput from '@/Components/ui/AppDateInput.vue';
import AppDropdown from '@/Components/ui/AppDropdown.vue';
import AppDropdownItem from '@/Components/ui/AppDropdownItem.vue';
import AppInput from '@/Components/ui/AppInput.vue';
import AppModal from '@/Components/ui/AppModal.vue';
import AppTextarea from '@/Components/ui/AppTextarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import { useToast } from '@/Composables/useToast';
import { errosDeValidacao } from '@/utils/erros';

/** Nova tarefa ou edição. "Aplicar modelo" pré-preenche a partir de um modelo do agente. */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    leadId: { type: Number, required: true },
    tarefa: { type: Object, default: null },
    modelos: { type: Array, default: () => [] },
});

const emit = defineEmits(['fechar', 'salvo']);
const toast = useToast();

const hojeIso = () => new Date().toISOString().slice(0, 10);
const form = reactive({ titulo: '', data_limite: hojeIso(), anotacao: '' });
const erros = ref({});
const salvando = ref(false);

watch(() => props.aberto, (aberto) => {
    if (!aberto) return;
    erros.value = {};
    Object.assign(form, props.tarefa
        ? { titulo: props.tarefa.titulo ?? '', data_limite: String(props.tarefa.data_limite ?? '').slice(0, 10), anotacao: props.tarefa.anotacao ?? '' }
        : { titulo: '', data_limite: hojeIso(), anotacao: '' });
}, { immediate: true });

function aplicarModelo(m) {
    const d = new Date();
    d.setDate(d.getDate() + (Number(m.prazo_dias) || 0));
    form.titulo = m.titulo;
    form.anotacao = m.anotacao ?? '';
    form.data_limite = d.toISOString().slice(0, 10);
}

async function salvar() {
    salvando.value = true;
    erros.value = {};
    try {
        if (props.tarefa) await axios.put(route('api.tarefas.update', props.tarefa.id), form, { silencioso: true });
        else await axios.post(route('api.leads.tarefas.store', props.leadId), form, { silencioso: true });
        toast.sucesso('Tarefa salva.');
        emit('salvo');
        emit('fechar');
    } catch (e) {
        erros.value = errosDeValidacao(e);
        if (!Object.keys(erros.value).length) toast.erro('Não foi possível salvar a tarefa.');
    } finally {
        salvando.value = false;
    }
}
</script>

<template>
    <AppModal :aberto="aberto" :titulo="tarefa ? 'Editar tarefa' : 'Nova tarefa'" tamanho="md" @fechar="emit('fechar')">
        <form id="form-tarefa" class="space-y-4" @submit.prevent="salvar">
            <div v-if="!tarefa && modelos.length" class="flex justify-end">
                <AppDropdown rotulo="Aplicar modelo" alinhar="end">
                    <template #gatilho="{ alternar, atributos }">
                        <AppButton variante="secondary" tamanho="sm" v-bind="atributos" @click="alternar"><template #icone><LayoutTemplate /></template>Aplicar modelo<template #iconeDireita><ChevronDown /></template></AppButton>
                    </template>
                    <AppDropdownItem v-for="m in modelos" :key="m.id" :rotulo="`${m.titulo} · +${m.prazo_dias} d`" @click="aplicarModelo(m)" />
                </AppDropdown>
            </div>
            <FormField rotulo="Título" obrigatorio :erro="erros.titulo">
                <AppInput v-model="form.titulo" :maxlength="255" placeholder="Ex.: Ligar para confirmar reunião" autofocus />
            </FormField>
            <FormField rotulo="Vencimento" obrigatorio :erro="erros.data_limite">
                <AppDateInput v-model="form.data_limite" />
            </FormField>
            <FormField rotulo="Anotação" :erro="erros.anotacao">
                <AppTextarea v-model="form.anotacao" :rows="3" placeholder="Opcional" />
            </FormField>
        </form>
        <template #rodape>
            <AppButton variante="secondary" @click="emit('fechar')">Cancelar</AppButton>
            <AppButton type="submit" form="form-tarefa" :carregando="salvando" :disabled="!form.titulo.trim() || !form.data_limite">{{ tarefa ? 'Salvar' : 'Criar tarefa' }}</AppButton>
        </template>
    </AppModal>
</template>
