import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UnsupportedFieldRendererShell from '../../resources/js/components/x-change/renderers/UnsupportedFieldRendererShell.vue';

describe('UnsupportedFieldRendererShell', () => {
    it('renders unsupported field shell using dynamic test ids', () => {
        const wrapper = mount(UnsupportedFieldRendererShell, {
            props: {
                testId: 'demo-unsupported-renderer',
                field: {
                    key: 'photo',
                    label: 'Photo',
                    type: 'camera',
                    required: false,
                },
            },
        });

        expect(wrapper.find('[data-testid="demo-unsupported-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="demo-unsupported-renderer-label"]').text()).toBe('Photo');
        expect(wrapper.find('[data-testid="demo-unsupported-renderer-kind"]').text()).toBe('unsupported field');
        expect(wrapper.find('[data-testid="demo-unsupported-renderer-type"]').text()).toBe('camera');
    });

    it('falls back to field key and unknown type', () => {
        const wrapper = mount(UnsupportedFieldRendererShell, {
            props: {
                testId: 'demo-unsupported-renderer',
                field: {
                    key: 'mystery',
                    required: false,
                },
            },
        });

        expect(wrapper.find('[data-testid="demo-unsupported-renderer-label"]').text()).toBe('mystery');
        expect(wrapper.find('[data-testid="demo-unsupported-renderer-type"]').text()).toBe('unknown');
    });
});
