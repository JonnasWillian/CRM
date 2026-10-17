<script setup>
/**
 * Chips de filtro: único (`modelValue` escalar) ou múltiplo (array). `cor`
 * desenha um ponto na cor do estágio ao lado do rótulo.
 */
const props = defineProps({
    modelValue: { type: [String, Number, Array, null], default: null },
    opcoes: { type: Array, required: true }, // [{ valor, rotulo, cor?, contagem? }]
    multiplo: { type: Boolean, default: false },
    rotulo: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const ativa = (valor) => (props.multiplo ? (props.modelValue ?? []).includes(valor) : props.modelValue === valor);

function alternar(valor) {
    if (!props.multiplo) return emit('update:modelValue', valor);
    const atual = new Set(props.modelValue ?? []);
    atual.has(valor) ? atual.delete(valor) : atual.add(valor);
    emit('update:modelValue', [...atual]);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5" :role="multiplo ? 'group' : 'radiogroup'" :aria-label="rotulo ?? undefined">
        <span v-if="rotulo" class="mr-1 text-xs font-medium uppercase tracking-wider text-fg-2">{{ rotulo }}</span>
        <button
            v-for="o in opcoes"
            :key="o.valor"
            type="button"
            :aria-pressed="ativa(o.valor) ? 'true' : 'false'"
            class="inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors duration-1 ease-ui"
            :class="ativa(o.valor) ? 'border-primary bg-primary-subtle text-primary' : 'border-line bg-bg-1 text-fg-1 hover:border-line-2 hover:text-fg-0'"
            @click="alternar(o.valor)"
        >
            <span v-if="o.cor" class="h-2 w-2 shrink-0 rounded-full" :style="{ background: o.cor }" aria-hidden="true" />
            {{ o.rotulo }}
            <span v-if="o.contagem !== undefined" class="num text-xs text-fg-2">{{ o.contagem }}</span>
        </button>
    </div>
</template>
