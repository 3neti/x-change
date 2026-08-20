import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SliceSelectorFieldRenderer from '../../resources/js/components/x-change/renderers/SliceSelectorFieldRenderer.vue';

describe('SliceSelectorFieldRenderer', () => {
    it('selects all available slices and excludes disabled slices', async () => {
        const wrapper = mount(SliceSelectorFieldRenderer, {
            global: {
                stubs: {
                    Button: {
                        emits: ['click'],
                        template: '<button type="button" @click="$emit(\'click\')"><slot /></button>',
                    },
                    Checkbox: {
                        props: ['modelValue', 'disabled'],
                        emits: ['update:modelValue'],
                        template: '<button type="button" data-testid="slice-checkbox" :data-state="modelValue ? \'checked\' : \'unchecked\'" :disabled="disabled" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
                    },
                    Badge: {
                        template: '<span><slot /></span>',
                    },
                },
            },
            props: {
                field: {
                    key: 'slice_ids',
                    type: 'slice_selector',
                    label: 'Slices to Redeem',
                    required: true,
                    options: [
                        {
                            id: 'slice_1',
                            amount: 6000,
                            description: 'Buy Product 1',
                            available: true,
                            disabled: false,
                        },
                        {
                            id: 'slice_2',
                            amount: 4000,
                            description: 'Pay for Service 1',
                            available: false,
                            disabled: true,
                            disabled_reason: 'Already claimed.',
                        },
                    ],
                },
                value: [],
            },
        });

        await wrapper.findAll('button').find((button) => button.text().includes('Select all'))?.trigger('click');

        expect(wrapper.emitted('update:value')?.at(-1)).toEqual([
            ['slice_1'],
        ]);

        await wrapper.setProps({ value: ['slice_1'] });

        expect(wrapper.get('[data-testid="slice-checkbox"]').attributes('data-state')).toBe('checked');
        expect(wrapper.text()).toContain('Clear all');

        await wrapper.findAll('button').find((button) => button.text().includes('Clear all'))?.trigger('click');

        expect(wrapper.emitted('update:value')?.at(-1)).toEqual([[]]);

        await wrapper.setProps({ value: [] });

        expect(wrapper.get('[data-testid="slice-checkbox"]').attributes('data-state')).toBe('unchecked');
        expect(wrapper.text()).toContain('Select all');
    });
});
