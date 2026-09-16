import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Accounts from '../../../resources/js/pages/x-change/cockpit/Accounts.vue';
import Campaigns from '../../../resources/js/pages/x-change/cockpit/Campaigns.vue';
import Dashboard from '../../../resources/js/pages/x-change/cockpit/Dashboard.vue';
import DistributionWorkspace from '../../../resources/js/pages/x-change/cockpit/DistributionWorkspace.vue';
import LeadCampaignScenarioRunner from '../../../resources/js/pages/x-change/cockpit/LeadCampaignScenarioRunner.vue';
import PayCodeExplorer from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';
import QuickGenerate from '../../../resources/js/pages/x-change/cockpit/QuickGenerate.vue';
import VoucherDetail from '../../../resources/js/pages/x-change/cockpit/VoucherDetail.vue';

const routerPostMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { post: routerPostMock },
}));

describe('Cockpit route page adapters', () => {
    it('mounts the Accounts route adapter', () => {
        const wrapper = mount(Accounts, {
            props: {
                account_overview: {
                    schema: 'x-change.cockpit.depositor-account.v1',
                    status: 'available',
                    account: {
                        reference: 'Account •••• 12345678',
                        currency: 'PHP',
                    },
                    funding_destinations: [],
                },
            },
        });

        expect(
            wrapper.find('[data-testid="cockpit-accounts-page"]').exists(),
        ).toBe(true);
    });

    it('mounts the dashboard route adapter', () => {
        const wrapper = mount(Dashboard);

        expect(
            wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists(),
        ).toBe(true);
    });

    it('mounts the quick generate route adapter', () => {
        const wrapper = mount(QuickGenerate);

        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the pay code explorer route adapter', () => {
        const wrapper = mount(PayCodeExplorer);

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-explorer-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the voucher detail route adapter', () => {
        const wrapper = mount(VoucherDetail);

        expect(
            wrapper
                .find('[data-testid="cockpit-voucher-detail-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the distribution workspace route adapter', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(
            wrapper
                .find('[data-testid="cockpit-distribution-workspace-shell"]')
                .exists(),
        ).toBe(true);
    });

    it('mounts the lead campaign scenario runner route adapter', () => {
        const wrapper = mount(LeadCampaignScenarioRunner, {
            props: {
                scenario: {
                    schema: 'x-change.cockpit.lead-campaign-scenario-runner.v1',
                    key: 'aui_on_demand_insurance_payment',
                    title: 'AUI On-Demand Insurance Payment',
                    description:
                        'Create a public lead campaign endpoint that mints a zero-denominated intake Pay Code.',
                    entry_point: 'Public QR/link',
                    person_type: 'Prospect',
                    pay_code_generation: 'On scan',
                    claim_surface: '/x/claim/{code}',
                    amount: '₱0.00',
                    action_url: '/x/cockpit/campaigns/lead-scenario-runner',
                    fields: ['Name', 'Mobile', 'Email'],
                },
                recent_lead_campaigns: [],
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-lead-scenario-runner"]')
                .exists(),
        ).toBe(true);
    });

    it('forwards endpoint campaign props through the campaigns route adapter', async () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [],
                campaign_usage_profiles: [
                    {
                        key: 'lead',
                        label: 'Lead',
                        description: 'Public QR or link.',
                        entry_point: 'Public QR/link',
                        person_type: 'Prospect',
                        pay_code_generation: 'On scan',
                        default_capabilities: ['public_link'],
                    },
                ],
                endpoint_capabilities: [
                    {
                        key: 'public_link',
                        label: 'Public link',
                    },
                ],
                pay_code_templates: [
                    {
                        id: 7,
                        reference: 'TPL-500',
                        name: 'Endpoint ₱500 Disbursement',
                        description: 'Reusable disbursement template.',
                        amount_minor: 50000,
                        currency: 'PHP',
                        flow_type: 'disbursable',
                        input_fields: [],
                    },
                ],
                endpoint_campaigns: [
                    {
                        reference: 'ENDPOINT-500',
                        title: 'Endpoint ₱500 Disbursement',
                        description:
                            'Public endpoint for a plain ₱500 disbursable Pay Code.',
                        status: 'active',
                        merchant_display_name: 'LYFE - Shaw',
                        merchant_slug: 'lyfe-shaw',
                        endpoint_slug: 'endpoint-500-disbursement',
                        public_url:
                            'https://x-change.test/x/o/lyfe-shaw/endpoint-500-disbursement',
                        qr_data_uri: null,
                        usage_count: 0,
                        starts_limit: null,
                        last_started_at: null,
                        expires_at: null,
                        usage_key: 'lead',
                        usage_label: 'Lead',
                        capabilities: ['public_link'],
                        availability: {},
                        limits: {},
                        template: {
                            id: 7,
                            reference: 'TPL-500',
                            name: 'Endpoint ₱500 Disbursement',
                            amount_minor: 50000,
                            currency: 'PHP',
                        },
                    },
                ],
                endpoint_campaign_form: {
                    action_url: '/x/cockpit/campaigns/endpoints',
                    default_timezone: 'Asia/Manila',
                },
            },
        });

        await wrapper
            .find('[data-testid="campaign-flavor-endpoints"]')
            .trigger('click');

        expect(wrapper.text()).toContain('1 live cards');
        expect(wrapper.text()).toContain('Endpoint ₱500 Disbursement');
        expect(wrapper.text()).toContain(
            'https://x-change.test/x/o/lyfe-shaw/endpoint-500-disbursement',
        );
        expect(
            wrapper.find('[data-testid="campaign-endpoint-list"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="campaign-endpoint-card-ENDPOINT-500"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain('No endpoint campaigns yet');
        expect(wrapper.text()).not.toContain(
            'Save a Pay Code template in Quick Generate',
        );
    });
});
