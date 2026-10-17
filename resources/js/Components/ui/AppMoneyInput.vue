<script setup>
import { ref, watch } from 'vue';
import { BORDA_CAMPO, CLASSES_CAMPO, TAMANHO_CAMPO, useCampo } from './campo';

/**
 * Valor em reais: aceita "1.500,00", "1500,5" ou "1500.5", emite número
 * (ou null quando vazio) e mostra formatado ao sair do campo. Corrige o
 * parse antigo, que trocava só a primeira vírgula e lia "1.500,00" como 1,5.
 */
const props = defineProps({
    modelValue: { type: Number, default: null },
    placeholder: { type: String, default: '0,00' },
    tamanho: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    name: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { invalido, atributos } = useCampo(props);

const formatar = (n) =>
    n === null || n === undefined ? '' : Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const texto = ref(formatar(props.modelValue));
let focado = false;

function interpretar(bruto) {
    const limpo = String(bruto).replace(/[^\d,.-]/g, '');
    if (limpo === '' || limpo === '-' ) return null;
    // Com vírgula, os pontos são milhar; sem vírgula, um ponto é decimal.
    const normalizado = limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo;
    const n = parseFloat(normalizado);
    return Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
}

function aoDigitar(e) {
    texto.value = e.target.value;
    emit('update:modelValue', interpretar(texto.value));
}

function aoSair() {
    focado = false;
    texto.value = formatar(props.modelValue);
}

watch(
    () => props.modelValue,
    (novo) => {
        if (!focado) texto.value = formatar(novo);
    },
);
</script>

<template>
    <div class="relative flex items-center">
        <span class="pointer-events-none absolute left-3 text-sm text-fg-2" aria-hidden="true">R$</span>
        <input
            v-bind="atributos"
            type="text"
            inputmode="decimal"
            :value="texto"
            :placeholder="placeholder"
            :disabled="disabled"
            :name="name"
            class="num pl-9 text-right"
            :class="[CLASSES_CAMPO, BORDA_CAMPO(invalido), TAMANHO_CAMPO[tamanho] ?? TAMANHO_CAMPO.md]"
            @focus="focado = true"
            @input="aoDigitar"
            @blur="aoSair"
        />
    </div>
</template>
