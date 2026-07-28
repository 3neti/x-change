import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitRiderEditorDisclosure from '../../../resources/js/cockpit/components/CockpitRiderEditorDisclosure.vue';

describe('Cockpit Rider editor disclosure', () => {
    it('keeps an editor compact while preserving its configured summary', async () => {
        const wrapper = mount(CockpitRiderEditorDisclosure, {
            props: {
                title: 'Rider Message',
                description: 'Add a recipient message.',
                status: 'Configured',
                summary: 'Birthday cash gift',
            },
            slots: {
                default:
                    '<textarea data-testid="rider-message">Birthday cash gift</textarea>',
            },
        });

        const disclosure = wrapper.get('details');

        expect(disclosure.attributes('open')).toBeUndefined();
        expect(wrapper.text()).toContain('Rider Message');
        expect(wrapper.text()).toContain('Configured');
        expect(wrapper.text()).toContain('Birthday cash gift');
        expect(wrapper.get('[data-testid="rider-message"]').exists()).toBe(
            true,
        );

        await wrapper.get('summary').trigger('click');

        expect(disclosure.attributes()).toHaveProperty('open');
        expect(
            wrapper.get('[data-testid="rider-message"]').element,
        ).toHaveProperty('value', 'Birthday cash gift');
    });

    it('can open the most relevant editor by default', () => {
        const wrapper = mount(CockpitRiderEditorDisclosure, {
            props: {
                title: 'Rider Stamp',
                description: 'Configure the shareable visual identity.',
                defaultOpen: true,
            },
        });

        expect(wrapper.get('details').attributes()).toHaveProperty('open');
        expect(wrapper.text()).toContain('Empty');
    });
});
