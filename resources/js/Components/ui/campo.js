import { computed, inject } from 'vue';

/** Atributos que todo campo recebe do FormField (ou nenhum, fora dele). */
export function useCampo(props) {
    const campo = inject('campoFormulario', null);

    const id = computed(() => props.id ?? campo?.id);
    const invalido = computed(() => campo?.invalido.value ?? false);

    const atributos = computed(() => ({
        id: id.value,
        'aria-invalid': invalido.value ? 'true' : undefined,
        'aria-required': campo?.obrigatorio.value ? 'true' : undefined,
        'aria-describedby': campo?.describedBy.value,
    }));

    return { id, invalido, atributos };
}

export const CLASSES_CAMPO =
    'block w-full rounded border bg-bg-1 text-fg-0 placeholder:text-fg-disabled transition-colors duration-1 ease-ui ' +
    'focus:border-focus focus:outline-none focus:ring-2 focus:ring-focus/25 ' +
    'disabled:cursor-not-allowed disabled:bg-bg-2 disabled:text-fg-2 read-only:bg-bg-2';

export const BORDA_CAMPO = (invalido) => (invalido ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line hover:border-line-2');

export const TAMANHO_CAMPO = { sm: 'h-8 px-2.5 text-sm', md: 'h-9 px-3 text-md' };
