import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitRiderMessageEditor from '../../../resources/js/cockpit/components/CockpitRiderMessageEditor.vue';

describe('Cockpit Rider Message editor', () => {
    it('applies constrained rich text markup without replacing the message', async () => {
        const wrapper = mount(CockpitRiderMessageEditor, {
            props: {
                message: 'Birthday gift',
                format: 'plain',
                'onUpdate:message': (value: string) =>
                    wrapper.setProps({ message: value }),
                'onUpdate:format': (value: string) =>
                    wrapper.setProps({ format: value }),
            },
        });
        const textarea = wrapper.get('textarea').element;

        textarea.setSelectionRange(0, 8);
        await wrapper.get('button[aria-label="Bold"]').trigger('click');

        expect(wrapper.props('format')).toBe('markdown');
        expect(wrapper.props('message')).toBe('**Birthday** gift');
        expect(
            wrapper
                .get('[data-testid="cockpit-rider-message-preview"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .get('[data-testid="cockpit-rider-message-preview"]')
                .attributes('srcdoc'),
        ).toContain('<strong>Birthday</strong>');
    });

    it('keeps plain text markup escaped inside the sandboxed preview', () => {
        const wrapper = mount(CockpitRiderMessageEditor, {
            props: {
                message: '<script>alert(1)</script>',
                format: 'plain',
            },
        });
        const preview = wrapper.get(
            '[data-testid="cockpit-rider-message-preview"]',
        );

        expect(preview.attributes('sandbox')).toBe('');
        expect(preview.attributes('srcdoc')).toContain(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        );
        expect(preview.attributes('srcdoc')).not.toContain(
            '<script>alert(1)</script>',
        );
    });
});
