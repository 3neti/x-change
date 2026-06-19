export const Checkbox = {
    name: 'Checkbox',
    props: ['checked', 'disabled'],
    emits: ['update:modelValue'],
    template: `
        <button
            type="button"
            data-testid="checkbox"
            :disabled="disabled"
            @click="$emit('update:modelValue', !checked)"
        >
            <slot />
        </button>
    `,
};
