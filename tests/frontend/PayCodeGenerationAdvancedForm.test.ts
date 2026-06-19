import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import PayCodeGenerationAdvancedForm from '../../resources/js/components/x-change/pay-codes/PayCodeGenerationAdvancedForm.vue';

vi.mock(
    '@/components/ui/card',
    () => ({
        Card: {
            template: '<div><slot /></div>',
        },
        CardContent: {
            template: '<div><slot /></div>',
        },
        CardDescription: {
            template: '<div><slot /></div>',
        },
        CardHeader: {
            template: '<div><slot /></div>',
        },
        CardTitle: {
            template: '<div><slot /></div>',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/input',
    () => ({
        Input: {
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template:
                '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/label',
    () => ({
        Label: {
            template: '<label><slot /></label>',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/checkbox',
    () => ({
        Checkbox: {
            props: ['checked'],
            emits: ['update:modelValue'],
            template: `
            <button
                type="button"
                data-testid="checkbox"
                @click="$emit('update:modelValue', !checked)"
            >
                {{ checked ? 'checked' : 'unchecked' }}
            </button>
        `,
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/textarea',
    () => ({
        Textarea: {
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template:
                '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/separator',
    () => ({
        Separator: {
            template: '<hr />',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/button',
    () => ({
        Button: {
            emits: ['click'],
            template:
                '<button type="button" @click="$emit(\'click\')"><slot /></button>',
        },
    }),
    { virtual: true },
);

vi.mock(
    '@/components/ui/badge',
    () => ({
        Badge: {
            template: '<span><slot /></span>',
        },
    }),
    { virtual: true },
);

vi.mock('lucide-vue-next', () => ({
    Plus: {
        template: '<span />',
    },
    Trash2: {
        template: '<span />',
    },
}));

describe('PayCodeGenerationAdvancedForm', () => {
    it('opens named slices on the first checkbox click', async () => {
        const Harness = defineComponent({
            components: { PayCodeGenerationAdvancedForm },
            setup() {
                const form = ref({
                    amount: 200,
                    named_slices_enabled: false,
                    named_slices: [],
                });

                return { form };
            },
            template: `
                <PayCodeGenerationAdvancedForm v-model="form" />
                <pre data-testid="state">{{ JSON.stringify(form) }}</pre>
            `,
        });

        const wrapper = mount(Harness);

        expect(wrapper.text()).not.toContain('Slice 1');

        const toggles = wrapper.findAll('[data-testid="checkbox"]');
        const namedSlicesToggle = toggles.at(-1);

        await namedSlicesToggle?.trigger('click');
        await nextTick();

        expect(wrapper.text()).toContain('Slice 1');
        expect(wrapper.find('[data-testid="state"]').text()).toContain(
            '"named_slices_enabled":true',
        );
        expect(wrapper.find('[data-testid="state"]').text()).toContain(
            '"description":"Whole amount"',
        );
    });
});
