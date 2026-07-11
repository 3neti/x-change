import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vitest';
import CockpitDiagnosticsDisclosure from '../../../resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue';
import CockpitGenerateActionPanel from '../../../resources/js/cockpit/components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../../../resources/js/cockpit/components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../../../resources/js/cockpit/components/CockpitPricingFundingSummary.vue';
import CockpitQuickGenerateAuthorizationGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateAuthorizationGatePanel.vue';
import CockpitQuickGenerateDraftContractPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateDraftContractPanel.vue';
import CockpitQuickGenerateFundingGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateFundingGatePanel.vue';
import CockpitQuickGenerateIdempotencyGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateIdempotencyGatePanel.vue';
import CockpitQuickGenerateMutationHandoffPlanPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationHandoffPlanPanel.vue';
import CockpitQuickGenerateMutationAuthorizationDecisionPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationAuthorizationDecisionPanel.vue';
import CockpitQuickGenerateMutationPreconditionsReviewPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationPreconditionsReviewPanel.vue';
import CockpitQuickGeneratePricingGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGeneratePricingGatePanel.vue';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import CockpitQuickGenerateValidationRedactionGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateValidationRedactionGatePanel.vue';
import CockpitRuntimeInputPanel from '../../../resources/js/cockpit/components/CockpitRuntimeInputPanel.vue';
import CockpitTemplateSelector from '../../../resources/js/cockpit/components/CockpitTemplateSelector.vue';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';
import {
    cockpitPricingFundingSummary,
    cockpitQuickGenerateTemplates,
    cockpitRuntimeInputs,
} from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        reload: vi.fn(),
    },
}));

describe('Cockpit Quick Generate foundation', () => {
    it('renders template selector placeholders as institutional products', () => {
        const wrapper = mount(CockpitTemplateSelector, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                selectedKey: 'money-changer',
            },
        });

        expect(wrapper.text()).toContain('Start from institutional products');
        expect(wrapper.text()).toContain('Money Changer');
        expect(wrapper.text()).toContain('OFW Remittance');
        expect(wrapper.text()).toContain('Settlement Envelope');
        expect(wrapper.findAll('[data-testid="cockpit-template-option"]')).toHaveLength(3);
    });

    it('renders runtime inputs as placeholders without a submit form', () => {
        const wrapper = mount(CockpitRuntimeInputPanel, {
            props: {
                inputs: cockpitRuntimeInputs,
            },
        });

        expect(wrapper.text()).toContain('Operator input placeholders');
        expect(wrapper.text()).toContain('Amount');
        expect(wrapper.text()).toContain('Recipient');
        expect(wrapper.text()).toContain('Purpose');
        expect(wrapper.text()).toContain('Use the Quick Generate form');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="cockpit-runtime-input"]')).toHaveLength(3);
    });

    it('renders pricing and funding summaries without calculating or reserving funds', () => {
        const wrapper = mount(CockpitPricingFundingSummary, {
            props: {
                summaries: cockpitPricingFundingSummary,
            },
        });

        expect(wrapper.text()).toContain('Pricing Estimate');
        expect(wrapper.text()).toContain('Shown after submit');
        expect(wrapper.text()).toContain('Funding Impact');
        expect(wrapper.text()).toContain('Existing handoff');
        expect(wrapper.text()).toContain('operator-safe funding preflight');
        expect(wrapper.findAll('[data-testid="cockpit-pricing-summary-item"]')).toHaveLength(3);
    });

    it('shows the generate action as an informational existing handoff status panel', async () => {
        const wrapper = mount(CockpitGenerateActionPanel, {
            props: {
                enabled: false,
                runtimeEnabled: true,
            },
        });

        const button = wrapper.find('[data-testid="cockpit-generate-button"]');

        expect(button.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('Existing issuance handoff');
        expect(wrapper.text()).toContain('Use Quick Generate form above');
        expect(wrapper.text()).toContain('Issuance owner remains GeneratePayCode');
        expect(wrapper.text()).toContain('Pricing and funding preflights are informational');
        expect(wrapper.text()).toContain('Journal, action, and feedback handoffs remain separately gated');

        await button.trigger('click');

        expect(wrapper.emitted()).toEqual({});
    });

    it('demotes historical gate panels behind a diagnostics disclosure', () => {
        const wrapper = mount(CockpitDiagnosticsDisclosure, {
            props: {
                title: 'Architecture history and gate diagnostics',
                summary: 'Older baseline panels remain available for engineering diagnostics.',
            },
            slots: {
                default: '<div data-testid="diagnostic-slot">Authorization Gate Baseline</div>',
            },
        });

        expect(wrapper.find('[data-testid="cockpit-diagnostics-disclosure"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Diagnostics');
        expect(wrapper.text()).toContain('Architecture history and gate diagnostics');
        expect(wrapper.text()).toContain('Show architecture history');
        expect(wrapper.find('[data-testid="diagnostic-slot"]').text()).toContain('Authorization Gate Baseline');
    });

    it('renders the issuance boundary plan without a mutation form', () => {
        const wrapper = mount(CockpitIssuanceBoundaryPanel);

        expect(wrapper.text()).toContain('Issuance Boundary Plan');
        expect(wrapper.text()).toContain('Existing issuance action');
        expect(wrapper.text()).toContain('GeneratePayCode');
        expect(wrapper.text()).toContain('Authorization');
        expect(wrapper.text()).toContain('Pricing');
        expect(wrapper.text()).toContain('Funding');
        expect(wrapper.text()).toContain('Idempotency');
        expect(wrapper.text()).toContain('Redaction');
        expect(wrapper.text()).toContain('current Quick Generate uses the approved handoff route.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-issuance-boundary-panel"]').exists()).toBe(true);
    });

    it('renders the request draft contract without persistence or submission behavior', () => {
        const wrapper = mount(CockpitQuickGenerateDraftContractPanel, {
            props: {
                draftContract: {
                    schema: 'x-change.cockpit.quick-generate-draft.v1',
                    status: 'draft_only',
                    template_key: 'money-changer',
                    amount: null,
                    currency: 'PHP',
                    recipient_reference: null,
                    purpose: null,
                    idempotency_key: null,
                    redactions: {
                        payloads: 'draft-shape-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Request Draft Contract');
        expect(wrapper.text()).toContain('x-change.cockpit.quick-generate-draft.v1');
        expect(wrapper.text()).toContain('template_key');
        expect(wrapper.text()).toContain('money-changer');
        expect(wrapper.text()).toContain('currency');
        expect(wrapper.text()).toContain('PHP');
        expect(wrapper.text()).toContain('idempotency_key');
        expect(wrapper.text()).toContain('Pending');
        expect(wrapper.text()).toContain('Drafts are local and read-only in Slice 18.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-draft-contract-panel"]').exists()).toBe(true);
    });

    it('submits a sanitized quick generate payload through the mutation contract route', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-UI-001',
                    links: {
                        cockpit_detail: '/x/cockpit/pay-codes/PC-UI-001',
                        cockpit_distribution: '/x/cockpit/pay-codes/PC-UI-001/distribution',
                    },
                },
                post_issuance_navigation: {
                    schema: 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
                    status: 'available',
                    auto_redirect: false,
                    items: [
                        {
                            key: 'detail',
                            label: 'Open Cockpit detail',
                            href: '/x/cockpit/pay-codes/PC-UI-001',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                        {
                            key: 'distribution',
                            label: 'Open Distribution workspace',
                            href: '/x/cockpit/pay-codes/PC-UI-001/distribution',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                    ],
                    redactions: {
                        payloads: 'post-issuance-navigation-only',
                    },
                },
                draft: {
                    status: 'compiled',
                    factory: 'CockpitQuickGenerateDraftFactoryContract',
                    compiler: 'CockpitIssuanceDraftCompilerContract',
                },
                preflight: {
                    pricing: {
                        status: 'estimated',
                        currency: 'PHP',
                        base_fee: 1.25,
                        total: 1.75,
                        blocking: false,
                    },
                    funding: {
                        status: 'checked',
                        authority: 'local_ledger',
                        sync_status: 'not_required',
                        authoritative: {
                            balance: 10000,
                            currency: 'PHP',
                        },
                    },
                },
                activity: {
                    schema: 'x-change.cockpit.operator-issuance-activity.v1',
                    status: 'recording-attempted-after-issuance',
                    presentation_only: true,
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-1',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        await wrapper.find('[data-testid="cockpit-quick-generate-submit-amount"]').setValue('99.50');
        await wrapper.find('[data-testid="cockpit-quick-generate-submit-recipient"]').setValue('09173011987');
        await wrapper.find('[data-testid="cockpit-quick-generate-submit-purpose"]').setValue('Operator test issuance');
        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const [url, options] = fetchMock.mock.calls[0];
        const payload = JSON.parse(options.body);

        expect(url).toBe('/x/cockpit/quick-generate');
        expect(options.method).toBe('POST');
        expect(options.headers['Idempotency-Key']).toBe('cockpit-ui-idempotency-1');
        expect(payload.cash).toEqual({
            amount: 99.5,
            currency: 'PHP',
            validation: {},
        });
        expect(payload.inputs).toEqual({
            fields: [],
        });
        expect(payload.count).toBe(1);
        expect(payload.feedback).toEqual({
            mobile: '09173011987',
        });
        expect(payload.rider).toEqual({
            message: 'Operator test issuance',
        });
        expect(payload.metadata.custom.cockpit).toEqual({
            template_key: 'money-changer',
            source: 'cockpit.quick-generate',
        });
        expect(JSON.stringify(payload)).not.toContain('wallet');
        expect(JSON.stringify(payload)).not.toContain('provider_payload');
        expect(wrapper.emitted('submitSuccess')).toHaveLength(1);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-result-link"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-UI-001');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-link-detail"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-UI-001');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-link-distribution"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-UI-001/distribution');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]').text()).toContain('Automatic redirect: disabled');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]').text()).toContain('read-only');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-runtime-preflight-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-pricing-preflight-card"]').text()).toContain('PHP 1.75');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-funding-preflight-card"]').text()).toContain('local_ledger');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-funding-preflight-card"]').text()).toContain('PHP 10000');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-runtime-metadata-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-draft-runtime-card"]').text()).toContain('compiled');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-activity-runtime-card"]').text()).toContain('x-change.cockpit.operator-issuance-activity.v1');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-activity-runtime-card"]').text()).toContain('yes');

        vi.unstubAllGlobals();
    });

    it('prefills Quick Generate from read-only campaign context without campaign mutation', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-CAMPAIGN-001',
                    links: {
                        cockpit_detail: '/x/cockpit/pay-codes/PC-CAMPAIGN-001',
                    },
                },
                campaign_attribution: {
                    schema: 'x-change.cockpit.quick-generate-campaign-attribution.v1',
                    status: 'available',
                    available: true,
                    read_only: true,
                    mutates_campaign: false,
                    planning_key: 'plan-35d',
                    execution_id: 'exec-35d',
                    campaign_id: 'campaign-35d',
                    audience_id: 'audience-35d',
                    recipient_id: 'recipient-35d',
                    source: 'campaign_cockpit',
                    generated_code: 'PC-CAMPAIGN-001',
                },
                post_issuance_navigation: {
                    schema: 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
                    status: 'available',
                    auto_redirect: false,
                    items: [
                        {
                            key: 'campaign_explorer',
                            label: 'Return to Campaign Explorer',
                            href: '/x/cockpit/pay-codes?campaign_planning_key=plan-35d&campaign_execution_id=exec-35d&campaign_source=campaign_cockpit&activity_code=PC-CAMPAIGN-001',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                        {
                            key: 'campaign_dashboard',
                            label: 'Return to Campaign Dashboard',
                            href: '/x/cockpit?campaign_planning_key=plan-35d&campaign_execution_id=exec-35d',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                    ],
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-campaign',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                campaignContext: {
                    schema: 'x-change.cockpit.quick-generate-campaign-context.v1',
                    status: 'available',
                    authorized: true,
                    read_only: true,
                    mutates_campaign: false,
                    planning_key: 'plan-35d',
                    execution_id: 'exec-35d',
                    campaign_id: 'campaign-35d',
                    audience_id: 'audience-35d',
                    recipient_id: 'recipient-35d',
                    source: 'campaign_cockpit',
                    draft: {
                        template_key: 'ofw-remittance',
                        amount: '500.00',
                        currency: 'PHP',
                        recipient_reference: '09173011987',
                        purpose: 'Campaign payout',
                    },
                    redactions: {
                        payloads: 'campaign-context-prefill-only',
                    },
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-quick-generate-campaign-context-panel"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Campaign context prefill');
        expect(wrapper.text()).toContain('plan-35d');
        expect(wrapper.text()).toContain('exec-35d');
        expect(wrapper.text()).toContain('campaign-35d');
        expect(wrapper.text()).toContain('does not mutate campaign state');
        expect((wrapper.find('[data-testid="cockpit-quick-generate-submit-template"]').element as HTMLSelectElement).value).toBe('ofw-remittance');
        expect((wrapper.find('[data-testid="cockpit-quick-generate-submit-amount"]').element as HTMLInputElement).value).toBe('500.00');
        expect((wrapper.find('[data-testid="cockpit-quick-generate-submit-recipient"]').element as HTMLInputElement).value).toBe('09173011987');
        expect((wrapper.find('[data-testid="cockpit-quick-generate-submit-purpose"]').element as HTMLTextAreaElement).value).toBe('Campaign payout');

        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
        await Promise.resolve();
        await Promise.resolve();

        const [, options] = fetchMock.mock.calls[0];
        const payload = JSON.parse(options.body);

        expect(payload.cash).toEqual({
            amount: 500,
            currency: 'PHP',
            validation: {},
        });
        expect(payload.feedback).toEqual({
            mobile: '09173011987',
        });
        expect(payload.rider).toEqual({
            message: 'Campaign payout',
        });
        expect(payload.metadata.campaign).toEqual({
            planning_key: 'plan-35d',
            execution_id: 'exec-35d',
            campaign_id: 'campaign-35d',
            audience_id: 'audience-35d',
            recipient_id: 'recipient-35d',
            source: 'campaign_cockpit',
            read_only: true,
            mutates_campaign: false,
        });
        expect(payload.metadata.custom.cockpit).toEqual({
            template_key: 'ofw-remittance',
            source: 'cockpit.quick-generate',
            campaign_context: 'read-model-prefill',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="cockpit-quick-generate-campaign-attribution-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-campaign-attribution-panel"]').text()).toContain('Campaign attribution');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-campaign-attribution-panel"]').text()).toContain('plan-35d');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-campaign-attribution-panel"]').text()).toContain('PC-CAMPAIGN-001');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-link-campaign_explorer"]').attributes('href')).toContain('campaign_planning_key=plan-35d');
        expect(wrapper.find('[data-testid="cockpit-quick-generate-post-issuance-link-campaign_dashboard"]').attributes('href')).toContain('campaign_execution_id=exec-35d');
        expect(JSON.stringify(payload)).not.toContain('campaign_payload');
        expect(JSON.stringify(payload)).not.toContain('provider_payload');
        expect(JSON.stringify(payload)).not.toContain('wallet');

        vi.unstubAllGlobals();
    });

    it('refreshes the quick generate read model only when the operator clicks refresh after success', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-UI-REFRESH',
                    links: {
                        cockpit_detail: '/x/cockpit/pay-codes/PC-UI-REFRESH',
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-refresh',
        });
        vi.mocked(router.reload).mockClear();

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });

        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(router.reload).not.toHaveBeenCalled();

        await wrapper.find('[data-testid="cockpit-quick-generate-refresh-button"]').trigger('click');

        expect(router.reload).toHaveBeenCalledWith({
            only: ['quick_generate_read_model'],
        });

        vi.unstubAllGlobals();
    });

    it('keeps quick generate submit disabled until a post route url is available', async () => {
        const fetchMock = vi.fn();

        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: null,
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        const button = wrapper.find('[data-testid="cockpit-quick-generate-submit-button"]');

        expect(button.attributes('disabled')).toBeDefined();

        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');

        expect(fetchMock).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });

    it('prevents duplicate in-flight quick generate submit requests', async () => {
        let resolveFetch: ((value: unknown) => void) | null = null;
        const fetchMock = vi.fn().mockReturnValue(new Promise((resolve) => {
            resolveFetch = resolve;
        }));

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-duplicate',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });

        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');
        await wrapper.find('[data-testid="cockpit-quick-generate-submit-panel"]').trigger('submit');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-submit-button"]').attributes('disabled')).toBeDefined();

        resolveFetch?.({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
            }),
        });
        await Promise.resolve();
        await Promise.resolve();

        vi.unstubAllGlobals();
    });

    it('renders authorization gate facts without enabling generation', () => {
        const wrapper = mount(CockpitQuickGenerateAuthorizationGatePanel, {
            props: {
                authorization: {
                    status: 'runtime-ready',
                    gates: [
                        {
                            key: 'operator-authenticated',
                            label: 'Operator Authenticated',
                            status: 'passed',
                            reason: 'Authenticated Cockpit GET route resolved.',
                        },
                        {
                            key: 'can-generate-pay-code',
                            label: 'Can Generate Pay Code',
                            status: 'passed',
                            reason: 'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
                        },
                    ],
                    redactions: {
                        payloads: 'authorization-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Authorization Runtime Diagnostics');
        expect(wrapper.text()).toContain('Operator Authenticated');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Can Generate Pay Code');
        expect(wrapper.text()).toContain('The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.');
        expect(wrapper.text()).toContain('Provider and money movement authority remain separately gated outside the Cockpit shell.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-authorization-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-authorization-gate"]')).toHaveLength(2);
    });

    it('renders pricing gate facts without calculating or reserving funds', () => {
        const wrapper = mount(CockpitQuickGeneratePricingGatePanel, {
            props: {
                pricingGate: {
                    status: 'runtime-informational',
                    checks: [
                        {
                            key: 'template-selected',
                            label: 'Template Selected',
                            status: 'passed',
                            reason: 'The Money Changer template is selected by default for the current Quick Generate runtime.',
                        },
                        {
                            key: 'pricing-service-wired',
                            label: 'Pricing Service Wired',
                            status: 'passed',
                            reason: 'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
                        },
                    ],
                    redactions: {
                        payloads: 'pricing-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Pricing Runtime Diagnostics');
        expect(wrapper.text()).toContain('Template Selected');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Pricing Service Wired');
        expect(wrapper.text()).toContain('runtime-informational');
        expect(wrapper.text()).toContain('The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.');
        expect(wrapper.text()).toContain('Cockpit still does not expose raw pricing payloads.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-pricing-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-pricing-gate-check"]')).toHaveLength(2);
    });

    it('renders funding gate facts without wallet access or reservation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateFundingGatePanel, {
            props: {
                fundingGate: {
                    status: 'runtime-informational',
                    checks: [
                        {
                            key: 'funding-policy-known',
                            label: 'Funding Policy Known',
                            status: 'passed',
                            reason: 'Funding preflight is represented as an operator-safe result after Quick Generate submits.',
                        },
                        {
                            key: 'issuer-wallet-identified',
                            label: 'Issuer Wallet Identified',
                            status: 'runtime-diagnostic',
                            reason: 'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
                        },
                    ],
                    redactions: {
                        payloads: 'funding-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Funding Runtime Diagnostics');
        expect(wrapper.text()).toContain('Funding Policy Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Issuer Wallet Identified');
        expect(wrapper.text()).toContain('runtime-informational');
        expect(wrapper.text()).toContain('Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.');
        expect(wrapper.text()).toContain('Cockpit still does not expose raw wallet or provider funding payloads.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-funding-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-funding-gate-check"]')).toHaveLength(2);
    });

    it('renders idempotency gate facts without persistence or replay behavior', () => {
        const wrapper = mount(CockpitQuickGenerateIdempotencyGatePanel, {
            props: {
                idempotencyGate: {
                    status: 'backend-ready',
                    checks: [
                        {
                            key: 'idempotency-policy-known',
                            label: 'Idempotency Policy Known',
                            status: 'passed',
                            reason: 'Idempotency is represented as a read-only Cockpit readiness fact.',
                        },
                        {
                            key: 'replay-lookup-ready',
                            label: 'Replay Lookup Ready',
                            status: 'blocked',
                            reason: 'Cockpit does not query idempotency stores or replay records in Slice 22.',
                        },
                    ],
                    redactions: {
                        payloads: 'idempotency-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Idempotency Gate Baseline');
        expect(wrapper.text()).toContain('Idempotency Policy Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Replay Lookup Ready');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('Cockpit does not query idempotency stores or replay records in Slice 22.');
        expect(wrapper.text()).toContain('Idempotency gates are read-only facts in Slice 22.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-idempotency-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-idempotency-gate-check"]')).toHaveLength(2);
    });

    it('renders validation and redaction gate facts without request validation or submitted payload exposure', () => {
        const wrapper = mount(CockpitQuickGenerateValidationRedactionGatePanel, {
            props: {
                validationRedactionGate: {
                    status: 'backend-ready',
                    checks: [
                        {
                            key: 'request-schema-known',
                            label: 'Request Schema Known',
                            status: 'passed',
                            reason: 'The Quick Generate mutation request shape is known and handled by the existing handoff route.',
                        },
                        {
                            key: 'sensitive-fields-redacted',
                            label: 'Sensitive Fields Redacted',
                            status: 'passed',
                            reason: 'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
                        },
                    ],
                    redactions: {
                        payloads: 'validation-redaction-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Validation and Redaction Diagnostics');
        expect(wrapper.text()).toContain('Request Schema Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Sensitive Fields Redacted');
        expect(wrapper.text()).toContain('backend-ready');
        expect(wrapper.text()).toContain('Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.');
        expect(wrapper.text()).toContain('These diagnostics do not expose request payloads');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-validation-redaction-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-validation-redaction-gate-check"]')).toHaveLength(2);
    });

    it('renders mutation handoff plan facts without mutation routes or generation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateMutationHandoffPlanPanel, {
            props: {
                mutationHandoffPlan: {
                    status: 'backend-handoff-wired',
                    steps: [
                        {
                            key: 'existing-issuance-owner-identified',
                            label: 'Existing Issuance Owner Identified',
                            status: 'passed',
                            reason: 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
                        },
                        {
                            key: 'generate-pay-code-action-handoff',
                            label: 'GeneratePayCode Action Handoff',
                            status: 'passed',
                            reason: 'Cockpit POST route calls the existing GeneratePayCode action through the approved handoff.',
                        },
                    ],
                    redactions: {
                        payloads: 'mutation-handoff-plan-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Handoff Diagnostics');
        expect(wrapper.text()).toContain('Existing Issuance Owner Identified');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('GeneratePayCode Action Handoff');
        expect(wrapper.text()).toContain('backend-handoff-wired');
        expect(wrapper.text()).toContain('Cockpit POST route calls the existing GeneratePayCode action through the approved handoff.');
        expect(wrapper.text()).toContain('Handoff diagnostics remain operator-safe');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-handoff-plan-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-mutation-handoff-plan-step"]')).toHaveLength(2);
    });

    it('renders mutation preconditions review facts without approving mutation wiring', () => {
        const wrapper = mount(CockpitQuickGenerateMutationPreconditionsReviewPanel, {
            props: {
                mutationPreconditionsReview: {
                    status: 'existing-handoff-ready',
                    recommendation: 'use-existing-issuance-handoff',
                    items: [
                        {
                            key: 'authorization-ready',
                            label: 'Authorization Ready',
                            status: 'passed',
                            reason: 'The authenticated Cockpit route may submit through the approved GeneratePayCode handoff.',
                        },
                        {
                            key: 'handoff-ready',
                            label: 'Handoff Ready',
                            status: 'passed',
                            reason: 'GeneratePayCode action handoff and GeneratePayCodeController handoff are wired.',
                        },
                    ],
                    redactions: {
                        payloads: 'mutation-preconditions-review-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Handoff Preconditions Diagnostics');
        expect(wrapper.text()).toContain('use-existing-issuance-handoff');
        expect(wrapper.text()).toContain('Authorization Ready');
        expect(wrapper.text()).toContain('Handoff Ready');
        expect(wrapper.text()).toContain('GeneratePayCode action handoff and GeneratePayCodeController handoff are wired.');
        expect(wrapper.text()).toContain('Provider, journal, action, feedback, and campaign mutations are not implied');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-preconditions-review-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-mutation-preconditions-review-item"]')).toHaveLength(2);
    });

    it('renders the mutation authorization decision point without registering mutation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateMutationAuthorizationDecisionPanel, {
            props: {
                mutationAuthorizationDecision: {
                    status: 'approved-handoff',
                    decision: 'authorized_existing_handoff',
                    required_approval: 'completed-for-existing-generate-pay-code-handoff',
                    rationale: 'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.',
                    next_step: 'keep-provider-journal-action-feedback-mutations-separately-gated',
                    redactions: {
                        payloads: 'mutation-authorization-decision-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Authorization Diagnostics');
        expect(wrapper.text()).toContain('authorized_existing_handoff');
        expect(wrapper.text()).toContain('completed-for-existing-generate-pay-code-handoff');
        expect(wrapper.text()).toContain('parallel issuance runtime');
        expect(wrapper.text()).toContain('keep-provider-journal-action-feedback-mutations-separately-gated');
        expect(wrapper.text()).toContain('Provider, journal, action, feedback, and campaign mutations remain separately gated.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-authorization-decision-panel"]').exists()).toBe(true);
    });

    it('renders the full Quick Generate page with active navigation and no side effects', () => {
        const wrapper = mount(QuickGenerate);

        expect(wrapper.find('[data-testid="cockpit-quick-generate-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Quick Generate Runtime');
        expect(wrapper.text()).toContain('Template Selector');
        expect(wrapper.text()).toContain('Runtime Inputs');
        expect(wrapper.text()).toContain('Pricing and Funding');
        expect(wrapper.text()).toContain('Generate Action');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Quick Generate');
        expect(wrapper.text()).toContain('template-first draft/compiler path');
        expect(wrapper.text()).toContain('GeneratePayCode action');
        expect(wrapper.text()).toContain('preflights are informational');
        expect(wrapper.text()).toContain('Issuance Boundary Plan');
        expect(wrapper.text()).toContain('current Quick Generate uses the approved handoff route.');
        expect(wrapper.text()).toContain('Request Draft Contract');
        expect(wrapper.text()).toContain('Drafts are local and read-only in Slice 18.');
        expect(wrapper.text()).toContain('Authorization Runtime Diagnostics');
        expect(wrapper.text()).toContain('Provider and money movement authority remain separately gated outside the Cockpit shell.');
        expect(wrapper.text()).toContain('Pricing Runtime Diagnostics');
        expect(wrapper.text()).toContain('Cockpit still does not expose raw pricing payloads.');
        expect(wrapper.text()).toContain('Funding Runtime Diagnostics');
        expect(wrapper.text()).toContain('Cockpit still does not expose raw wallet or provider funding payloads.');
        expect(wrapper.text()).toContain('Idempotency Gate Baseline');
        expect(wrapper.text()).toContain('Idempotency gates are read-only facts in Slice 22.');
        expect(wrapper.text()).toContain('Validation and Redaction Diagnostics');
        expect(wrapper.text()).toContain('These diagnostics do not expose request payloads');
        expect(wrapper.text()).toContain('Mutation Handoff Diagnostics');
        expect(wrapper.text()).toContain('Handoff diagnostics remain operator-safe');
        expect(wrapper.text()).toContain('Handoff Preconditions Diagnostics');
        expect(wrapper.text()).toContain('Provider, journal, action, feedback, and campaign mutations are not implied');
        expect(wrapper.text()).toContain('Mutation Authorization Diagnostics');
        expect(wrapper.text()).toContain('Provider, journal, action, feedback, and campaign mutations remain separately gated.');
    });
});
