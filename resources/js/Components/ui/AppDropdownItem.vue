<script setup>
import { inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    rotulo: { type: String, required: true },
    icone: { type: [Object, Function], default: null },
    tom: { type: String, default: 'default' }, // default | danger
    disabled: { type: Boolean, default: false },
    as: { type: String, default: 'button' }, // button | Link | a
    href: { type: String, default: undefined },
    method: { type: String, default: undefined },
});

const emit = defineEmits(['click']);
const dropdown = inject('dropdown', null);

function aoClicar(e) {
    if (props.disabled) return;
    emit('click', e);
    dropdown?.fechar(props.as === 'button');
}
</script>

<template>
    <component
        :is="as === 'Link' ? Link : as"
        role="menuitem"
        tabindex="-1"
        :type="as === 'button' ? 'button' : undefined"
        :href="as === 'button' ? undefined : href"
        :method="as === 'Link' ? method : undefined"
        :disabled="as === 'button' && disabled ? true : undefined"
        :aria-disabled="disabled ? 'true' : undefined"
        class="flex w-full items-center gap-2.5 rounded px-2.5 py-2 text-left text-md transition-colors duration-1 ease-ui focus:outline-none"
        :class="[
            tom === 'danger' ? 'text-danger hover:bg-danger-subtle focus:bg-danger-subtle' : 'text-fg-0 hover:bg-bg-2 focus:bg-bg-2',
            disabled ? 'cursor-not-allowed opacity-50' : '',
        ]"
        @click="aoClicar"
    >
        <component :is="icone" v-if="icone" :size="16" class="shrink-0" :class="tom === 'danger' ? '' : 'text-fg-2'" aria-hidden="true" />
        <span class="truncate">{{ rotulo }}</span>
    </component>
</template>
