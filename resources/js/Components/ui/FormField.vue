<script setup>
import { computed, provide, useId } from 'vue';

/**
 * Rótulo, campo, dica e erro ligados por id e aria. O campo filho (AppInput,
 * AppSelect…) recebe id, aria-invalid, aria-required e aria-describedby por
 * provide/inject — ou pelo slot com escopo, para campos fora da biblioteca.
 *
 * `erro` aceita string ou a lista do backend (`form.errors.x` /
 * `errosDeValidacao(e).x`); mostra a primeira mensagem.
 */
const props = defineProps({
    rotulo: { type: String, required: true },
    obrigatorio: { type: Boolean, default: false },
    dica: { type: String, default: null },
    erro: { type: [String, Array], default: null },
    id: { type: String, default: null },
    inline: { type: Boolean, default: false }, // rótulo ao lado (checkbox, switch)
});

const idCampo = props.id ?? `campo-${useId()}`;
const idDica = `${idCampo}-dica`;
const idErro = `${idCampo}-erro`;

const mensagem = computed(() => (Array.isArray(props.erro) ? (props.erro[0] ?? null) : props.erro || null));
const invalido = computed(() => Boolean(mensagem.value));
const obrigatorio = computed(() => props.obrigatorio);
const describedBy = computed(
    () => [props.dica ? idDica : null, invalido.value ? idErro : null].filter(Boolean).join(' ') || undefined,
);

provide('campoFormulario', { id: idCampo, invalido, obrigatorio, describedBy });
</script>

<template>
    <div class="min-w-0" :class="inline ? 'flex items-start gap-2' : 'space-y-1.5'">
        <template v-if="inline">
            <slot :id="idCampo" :invalido="invalido" :described-by="describedBy" />
        </template>
        <div class="min-w-0 flex-1" :class="{ 'space-y-1.5': !inline }">
            <label :for="idCampo" class="block text-sm font-medium text-fg-1">
                {{ rotulo }}
                <span v-if="obrigatorio" class="text-danger" aria-hidden="true">*</span>
            </label>
            <slot v-if="!inline" :id="idCampo" :invalido="invalido" :described-by="describedBy" />
            <p v-if="dica" :id="idDica" class="text-sm text-fg-2">{{ dica }}</p>
            <p v-if="invalido" :id="idErro" role="alert" class="text-sm text-danger">{{ mensagem }}</p>
        </div>
    </div>
</template>
