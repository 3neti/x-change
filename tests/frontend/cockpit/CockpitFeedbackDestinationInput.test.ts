import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitFeedbackDestinationInput from '../../../resources/js/cockpit/components/CockpitFeedbackDestinationInput.vue';
import { emptyFeedbackDestinations } from '../../../resources/js/cockpit/feedbackDestinations';

describe('CockpitFeedbackDestinationInput', () => {
    it('turns a mixed paste into normalized channel chips', async () => {
        const wrapper = mount(CockpitFeedbackDestinationInput, {
            props: {
                modelValue: emptyFeedbackDestinations(),
                'onUpdate:modelValue': (value) =>
                    wrapper.setProps({ modelValue: value }),
            },
        });
        const editor = wrapper.get(
            '[data-testid="cockpit-feedback-destination-editor"]',
        );

        await editor.trigger('paste', {
            clipboardData: {
                getData: () =>
                    'Issuer@Example.com;09173011987 https://example.test/hook',
            },
        });

        expect(wrapper.props('modelValue')).toEqual({
            email: 'issuer@example.com',
            mobile: '+639173011987',
            webhook: 'https://example.test/hook',
        });
        expect(
            wrapper.findAll('[data-testid^="cockpit-feedback-destination-"]'),
        ).not.toHaveLength(0);
    });

    it('keeps invalid values visible and reports a blocking error', async () => {
        const wrapper = mount(CockpitFeedbackDestinationInput, {
            props: {
                modelValue: emptyFeedbackDestinations(),
            },
        });

        await wrapper
            .get('[data-testid="cockpit-feedback-destination-editor"]')
            .setValue('not-a-destination');
        await wrapper
            .get('[data-testid="cockpit-feedback-destination-editor"]')
            .trigger('keydown', { key: 'Enter' });

        expect(
            wrapper
                .get('[data-testid="cockpit-feedback-destination-invalid"]')
                .text(),
        ).toContain('not-a-destination');
        expect(
            wrapper
                .get('[data-testid="cockpit-feedback-destination-errors"]')
                .text(),
        ).toContain('is not a valid email');
        expect(wrapper.emitted('validation')?.at(-1)?.[0]).toHaveLength(1);
    });

    it('offers saved destinations without coupling them to a checkbox', async () => {
        const wrapper = mount(CockpitFeedbackDestinationInput, {
            props: {
                modelValue: emptyFeedbackDestinations(),
                defaults: {
                    email: 'saved@example.com',
                    mobile: '+639173011987',
                },
                'onUpdate:modelValue': (value) =>
                    wrapper.setProps({ modelValue: value }),
            },
        });

        await wrapper
            .get(
                '[data-testid="cockpit-feedback-destination-suggestion-email"]',
            )
            .trigger('click');

        expect(wrapper.props('modelValue').email).toBe('saved@example.com');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-feedback-destination-suggestion-email"]',
                )
                .exists(),
        ).toBe(false);
    });
});
