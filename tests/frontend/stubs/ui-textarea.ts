export const Textarea = {
    name: 'Textarea',
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template:
        '<textarea data-testid="textarea" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
};
