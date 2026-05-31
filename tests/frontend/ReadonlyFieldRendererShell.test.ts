import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ReadonlyFieldRendererShell from '../../resources/js/components/x-change/renderers/ReadonlyFieldRendererShell.vue';

describe('ReadonlyFieldRendererShell', () => {
    it('renders a readonly field shell using dynamic test ids', () => {
        const wrapper = mount(ReadonlyFieldRendererShell, {
            props: {
                testId: 'demo-field-renderer',
                kind: 'demo field',
                field: {
                    key: 'demo',
                    label: 'Demo Field',
                    type: 'text',
                    required: true,
                },
            },
        });

        expect(wrapper.find('[data-testid="demo-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-field-renderer-label"]').text()).toBe('Demo Field');
        expect(wrapper.find('[data-testid="demo-field-renderer-kind"]').text()).toBe('demo field');
        expect(wrapper.find('[data-testid="demo-field-renderer-required"]').text()).toBe('required');
    });

    it('falls back to field key when label is absent', () => {
        const wrapper = mount(ReadonlyFieldRendererShell, {
            props: {
                testId: 'demo-field-renderer',
                kind: 'demo field',
                field: {
                    key: 'demo',
                    type: 'text',
                    required: false,
                },
            },
        });

        expect(wrapper.find('[data-testid="demo-field-renderer-label"]').text()).toBe('demo');
        expect(wrapper.find('[data-testid="demo-field-renderer-required"]').text()).toBe('optional');
    });
});
