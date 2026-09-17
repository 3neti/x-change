import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LeadCampaignScenarioRunner from '../../../resources/js/cockpit/pages/LeadCampaignScenarioRunner.vue';

const routerPostMock = vi.hoisted(() => vi.fn());
const routerGetMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { get: routerGetMock, post: routerPostMock },
}));

const scenario = {
    schema: 'x-change.cockpit.lead-campaign-scenario-runner.v2',
    key: 'disbursable_feedback_endpoint',
    title: '₱25 Disbursable Feedback Endpoint',
    description:
        'Create a one-use public campaign endpoint that mints a ₱25 disbursable Pay Code and reports lifecycle feedback by SMS.',
    entry_point: 'Public QR/link',
    person_type: 'Claimant',
    pay_code_generation: 'On scan',
    claim_surface: '/x/claim/{code}',
    amount: '₱25.00',
    details_label: 'Template instructions',
    details_description:
        'The saved template remains the source of truth for every Pay Code minted by this test endpoint.',
    fields: [
        'Disbursable',
        'Feedback SMS · 09173011987',
        'Rider message · test feedback',
        'One start',
        '₱25 budget cap',
        'Expires after 24 hours',
    ],
};

const auiScenario = {
    ...scenario,
    key: 'aui_on_demand_insurance_payment',
    title: 'AUI On-Demand Insurance Payment',
    person_type: 'Prospect',
    amount: '₱0.00',
};

describe('Lead Campaign scenario runner', () => {
    it('presents the disbursable feedback endpoint browser scenario', () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario,
                scenarios: [scenario, auiScenario],
                recent_lead_campaigns: [],
            },
        });

        expect(wrapper.text()).toContain('₱25 Disbursable Feedback Endpoint');
        expect(wrapper.text()).toContain('Public QR/link');
        expect(wrapper.text()).toContain('Claimant');
        expect(wrapper.text()).toContain('/x/claim/{code}');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('09173011987');
        expect(wrapper.text()).toContain('test feedback');
        expect(wrapper.get('[data-testid="cockpit-lead-scenario-run-button"]').text()).toContain(
            'Run in browser',
        );
    });

    it('posts to the browser runner endpoint when launched', async () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario,
                scenarios: [scenario, auiScenario],
                recent_lead_campaigns: [],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-lead-scenario-run-button"]')
            .trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/lead-scenario-runner',
            { scenario: 'disbursable_feedback_endpoint' },
            expect.objectContaining({
                preserveScroll: false,
            }),
        );
    });

    it('switches browser scenarios through the typed controller route', async () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario,
                scenarios: [scenario, auiScenario],
                recent_lead_campaigns: [],
            },
        });

        await wrapper
            .get(
                '[data-testid="cockpit-lead-scenario-option-aui_on_demand_insurance_payment"]',
            )
            .trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/lead-scenario-runner',
            { scenario: 'aui_on_demand_insurance_payment' },
            { preserveScroll: true },
        );
    });
});
