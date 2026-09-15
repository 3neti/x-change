import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LeadCampaignScenarioRunner from '../../../resources/js/cockpit/pages/LeadCampaignScenarioRunner.vue';

const routerPostMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { post: routerPostMock },
}));

const scenario = {
    schema: 'x-change.cockpit.lead-campaign-scenario-runner.v1',
    key: 'aui_on_demand_insurance_payment',
    title: 'AUI On-Demand Insurance Payment',
    description:
        'Create a public lead campaign endpoint that mints a zero-denominated intake Pay Code and routes the prospect through the normal claim UX.',
    entry_point: 'Public QR/link',
    person_type: 'Prospect',
    pay_code_generation: 'On scan',
    claim_surface: '/x/claim/{code}',
    amount: '₱0.00',
    action_url: '/x/cockpit/campaigns/lead-scenario-runner',
    fields: [
        'Name',
        'Mobile',
        'Email',
        'Address',
        'Birthday',
        'Insurance product',
        'Vehicle registration number',
        'Driver license number',
        'Payment reference',
    ],
};

describe('Lead Campaign scenario runner', () => {
    it('presents the AUI on-demand insurance payment browser scenario', () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario,
                recent_lead_campaigns: [],
            },
        });

        expect(wrapper.text()).toContain('AUI On-Demand Insurance Payment');
        expect(wrapper.text()).toContain('Public QR/link');
        expect(wrapper.text()).toContain('Prospect');
        expect(wrapper.text()).toContain('/x/claim/{code}');
        expect(wrapper.text()).toContain('₱0.00');
        expect(wrapper.text()).toContain('Insurance product');
        expect(wrapper.text()).toContain('Vehicle registration number');
        expect(wrapper.get('[data-testid="cockpit-lead-scenario-run-button"]').text()).toContain(
            'Run in browser',
        );
    });

    it('posts to the browser runner endpoint when launched', async () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario,
                recent_lead_campaigns: [],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-lead-scenario-run-button"]')
            .trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/lead-scenario-runner',
            {},
            expect.objectContaining({
                preserveScroll: false,
            }),
        );
    });
});
