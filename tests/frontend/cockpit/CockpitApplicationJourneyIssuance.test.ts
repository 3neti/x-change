import { config, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href?.url ?? href"><slot /></a>' },
    router: { reload: vi.fn() },
    usePage: vi.fn(() => ({ props: {} })),
}));

config.global.stubs = { ...config.global.stubs, Teleport: true };

function preview(wrapper: ReturnType<typeof mount>): Record<string, any> {
    return JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
}

describe('application journey issuance intake', () => {
    it('records a loan reference in the voucher instruction preview', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                applicationJourney: { key: 'loan_disbursement', label: 'Loan Disbursement', kind: 'issue_single' },
            },
        });

        expect(wrapper.get('[data-testid="cockpit-app-journey-intake"]').text()).toContain('Loan reference');
        await wrapper.get('[data-testid="cockpit-app-journey-reference"]').setValue('LOAN-42');

        expect(preview(wrapper).metadata.custom.app_journey).toEqual({
            key: 'loan_disbursement',
            reference: 'LOAN-42',
        });
        wrapper.unmount();
    });

    it('captures external quote inputs without calculating a payout amount', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                applicationJourney: { key: 'exchange_payout', label: 'Exchange Payout', kind: 'issue_single' },
            },
        });

        await wrapper.get('[data-testid="cockpit-app-journey-reference"]').setValue('QUOTE-42');
        await wrapper.get('[data-testid="cockpit-app-journey-source-currency"]').setValue('usd');
        await wrapper.get('[data-testid="cockpit-app-journey-source-amount"]').setValue('1.00');

        expect(preview(wrapper).metadata.custom.app_journey).toEqual({
            key: 'exchange_payout',
            reference: 'QUOTE-42',
            source_currency: 'USD',
            source_amount: '1.00',
        });
        expect(wrapper.get('[data-testid="cockpit-app-journey-intake"]').text()).toContain('No exchange rate is calculated here.');
        wrapper.unmount();
    });
});
