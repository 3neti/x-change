import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: {} }),
}));

vi.mock('@/composables/useIssuanceFundingPreflight', async () => {
    const { ref } = await import('vue');
    return {
        useIssuanceFundingPreflight: () => ({
            preflight: ref({ funding: { available_client_funds_minor: 9876543, external_amount_minor: 500, execution: 'payment_required' } }),
            preflighting: ref(false),
            preflightError: ref(null),
            refreshPreflight: vi.fn(),
        }),
    };
});

describe('Order funding privacy', () => {
    it('shows the additional funding without exposing the balance hidden by the header', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props: { templates: cockpitQuickGenerateTemplates } });
        const summary = wrapper.get('[data-testid="cockpit-funding-summary"]');
        expect(summary.text()).toContain('Additional funding required');
        expect(summary.text()).toContain('₱5.00');
        expect(summary.text()).not.toContain('98,765.43');
        expect(summary.text()).not.toContain('Available Client Funds');
    });
});
