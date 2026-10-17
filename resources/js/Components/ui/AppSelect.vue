<script setup>
import { ChevronDown } from 'lucide-vue-next';
import { BORDA_CAMPO, CLASSES_CAMPO, TAMANHO_CAMPO, useCampo } from './campo';

/**
 * <select> nativo (teclado e leitor de tela grátis) com seta própria.
 * `opcoes`: [{ valor, rotulo, disabled? }]; o valor emitido é o original
 * (número continua número), não a string do DOM.
 */
const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    opcoes: { type: Array, default: null },
    placeholder: { type: String, default: null },
    tamanho: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
    name: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { invalido, atributos } = useCampo(props);

function aoMudar(e) {
    const bruto = e.target.value;
    if (!props.opcoes) return emit('update:modelValue', bruto);
    const opcao = props.opcoes.find((o) => String(o.valor) === bruto);
    emit('update:modelValue', opcao ? opcao.valor : bruto === '' ? null : bruto);
}
</script>

<template>
    <div class="relative">
        <select
            v-bind="atributos"
            :value="modelValue ?? ''"
            :disabled="disabled"
            :name="name"
            class="appearance-none pr-9"
            :class="[CLASSES_CAMPO, BORDA_CAMPO(invalido), TAMANHO_CAMPO[tamanho] ?? TAMANHO_CAMPO.md]"
            @change="aoMudar"
        >
            <option v-if="placeholder !== null" value="">{{ placeholder }}</option>
            <template v-if="opcoes">
                <option v-for="o in opcoes" :key="o.valor" :value="o.valor" :disabled="o.disabled">{{ o.rotulo }}</option>
            </template>
            <slot v-else />
        </select>
        <ChevronDown :size="16" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-fg-2" aria-hidden="true" />
    </div>
</template>
