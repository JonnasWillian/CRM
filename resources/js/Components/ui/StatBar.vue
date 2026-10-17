<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Barras comparativas (leads por estágio, perdas por motivo, ganhos por
 * mês). Uma cor só por padrão; `cor` por item só quando a cor tem nome ao
 * lado (estágio). Sem biblioteca: é div com largura.
 */
const props = defineProps({
    itens: { type: Array, required: true }, // [{ rotulo, valor, cor?, href? }]
    max: { type: Number, default: null },
    formatar: { type: Function, default: (v) => Number(v ?? 0).toLocaleString('pt-BR') },
    orientacao: { type: String, default: 'horizontal' }, // horizontal | vertical
});

const maior = computed(() => Math.max(1, props.max ?? Math.max(...props.itens.map((i) => Number(i.valor) || 0), 0)));
const percentual = (valor) => `${Math.round(((Number(valor) || 0) / maior.value) * 100)}%`;
</script>

<template>
    <ul v-if="orientacao === 'vertical'" class="flex h-40 items-end gap-2" role="list">
        <li v-for="(item, i) in itens" :key="i" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
            <span class="num text-xs text-fg-2">{{ formatar(item.valor) }}</span>
            <span
                data-barra
                class="w-full rounded-t-sm bg-primary"
                :style="{ height: percentual(item.valor), background: item.cor || undefined }"
                :title="`${item.rotulo}: ${formatar(item.valor)}`"
            />
            <span class="w-full truncate text-center text-xs text-fg-2">{{ item.rotulo }}</span>
        </li>
    </ul>
    <ul v-else class="space-y-2.5" role="list">
        <li v-for="(item, i) in itens" :key="i">
            <component :is="item.href ? Link : 'div'" :href="item.href" class="block" :class="item.href ? 'rounded hover:bg-bg-2/60' : ''">
                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2 text-fg-1">
                        <span v-if="item.cor" class="h-2 w-2 shrink-0 rounded-full" :style="{ background: item.cor }" aria-hidden="true" />
                        <span class="truncate">{{ item.rotulo }}</span>
                    </span>
                    <span class="num shrink-0 font-medium text-fg-0">{{ formatar(item.valor) }}</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-bg-2">
                    <div
                        data-barra
                        class="h-full rounded-full bg-primary transition-[width] duration-3 ease-ui"
                        :style="{ width: percentual(item.valor), background: item.cor || undefined }"
                    />
                </div>
            </component>
        </li>
    </ul>
</template>
