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
                        props: ['checked', 'disabled'],
                        emits: ['update:modelValue'],
                        template: '<button type="button" data-testid="slice-checkbox" :disabled="disabled" @click="$emit(\'update:modelValue\', !checked)"><slot /></button>',
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
    });
});
