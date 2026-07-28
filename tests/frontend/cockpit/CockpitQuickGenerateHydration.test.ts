import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';
import RouteQuickGenerate from '../../../resources/js/pages/x-change/cockpit/QuickGenerate.vue';

const quickGenerateReadModel = {
    status: 'available',
    authorized: true,
    templates: [
        {
            key: 'institutional-cash',
            name: 'Institutional Cash',
            description: 'Sanitized operator-facing template.',
            profile: 'branch',
            estimated_time: 'Under 10 seconds',
            disabled: false,
            provider_payload: 'must-not-render',
        },
    ],
    runtime_inputs: [
        {
            key: 'amount',
            label: 'Amount',
            value: 'Pending amount',
            helper: 'No calculation executed.',
            wallet: 'must-not-render',
        },
    ],
    pricing_summaries: [
        {
            key: 'pricing',
            label: 'Pricing Estimate',
            value: 'Shown after submit',
            helper: 'Existing pricing preflight returned.',
            funding_source: 'must-not-render',
        },
    ],
    action: {
        enabled: true,
        reason: 'must-remain-disabled-in-ui',
    },
    mutation_contract: {
        runtime_enabled: true,
        route: 'x-change.cockpit.quick-generate.store',
        route_url: '/x/cockpit/quick-generate',
        allowed_methods: ['GET', 'POST'],
    },
    redactions: {
        payloads: 'sanitized-quick-generate-catalog-only',
    },
};

describe('Cockpit Quick Generate hydration', () => {
    it('hydrates sanitized template choices without restoring retired reference panels', () => {
        const wrapper = mount(QuickGenerate, {
            props: {
                quick_generate_read_model: quickGenerateReadModel,
            },
        });

        const templateSelect = wrapper.find(
            '[data-testid="cockpit-quick-generate-submit-template"]',
        );

        expect(templateSelect.text()).toContain('Institutional Cash');
        expect(
            templateSelect.find('option[value="institutional-cash"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain(
            'Sanitized operator-facing template.',
        );
        expect(wrapper.text()).not.toContain('Pending amount');
        expect(wrapper.text()).not.toContain(
            'Existing pricing preflight returned.',
        );
    });

    it('does not render unsafe quick generate payload fields or enable generation from read model props', () => {
        const wrapper = mount(QuickGenerate, {
            props: {
                quick_generate_read_model: quickGenerateReadModel,
            },
        });

        const text = wrapper.text();
        const submitButton = wrapper.find(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );

        expect(text).not.toContain('must-not-render');
        expect(text).not.toContain('funding_source');
        expect(
            wrapper.find('[data-testid="cockpit-generate-button"]').exists(),
        ).toBe(false);
        expect(submitButton.attributes('disabled')).toBeUndefined();
    });

    it('keeps static defaults when the read model is missing or unauthorized', () => {
        const wrapper = mount(QuickGenerate, {
            props: {
                quick_generate_read_model: {
                    status: 'not_wired',
                    authorized: false,
                    templates: [],
                    runtime_inputs: [],
                    pricing_summaries: [],
                    action: {
                        enabled: false,
                        reason: 'not-loaded',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Money Changer');
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-submit-template"]')
                .text(),
        ).toContain('Money Changer');
        expect(wrapper.text()).toContain('Ready to issue');
    });

    it('forwards route adapter props into the cockpit quick generate page', () => {
        const wrapper = mount(RouteQuickGenerate, {
            props: {
                quick_generate_read_model: quickGenerateReadModel,
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-submit-template"]')
                .text(),
        ).toContain('Institutional Cash');
        expect(wrapper.text()).not.toContain('Pending amount');
    });
});
