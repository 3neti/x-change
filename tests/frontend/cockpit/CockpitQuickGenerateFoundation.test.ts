import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vitest';
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
        expect(wrapper.text()).toContain('Not calculated');
        expect(wrapper.text()).toContain('Funding Impact');
        expect(wrapper.text()).toContain('Not reserved');
        expect(wrapper.text()).toContain('No wallet lookup, reservation, debit, or provider call occurs here.');
        expect(wrapper.findAll('[data-testid="cockpit-pricing-summary-item"]')).toHaveLength(3);
    });

    it('keeps the generate action disabled until issuance is explicitly wired', async () => {
        const wrapper = mount(CockpitGenerateActionPanel, {
            props: {
                enabled: false,
            },
        });

        const button = wrapper.find('[data-testid="cockpit-generate-button"]');

        expect(button.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('No voucher generation');
        expect(wrapper.text()).toContain('No wallet debit or reservation');
        expect(wrapper.text()).toContain('No provider call');
        expect(wrapper.text()).toContain('No journal or feedback side effect');

        await button.trigger('click');

        expect(wrapper.emitted()).toEqual({});
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
        expect(wrapper.text()).toContain('No Cockpit mutation route is registered in Slice 17.');
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
                    },
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
            preserveScroll: true,
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
                    status: 'blocked',
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
                            status: 'blocked',
                            reason: 'No Cockpit mutation route is registered.',
                        },
                    ],
                    redactions: {
                        payloads: 'authorization-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Authorization Gate Baseline');
        expect(wrapper.text()).toContain('Operator Authenticated');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Can Generate Pay Code');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('No Cockpit mutation route is registered.');
        expect(wrapper.text()).toContain('Authorization gates are read-only facts in Slice 19.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-authorization-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-authorization-gate"]')).toHaveLength(2);
    });

    it('renders pricing gate facts without calculating or reserving funds', () => {
        const wrapper = mount(CockpitQuickGeneratePricingGatePanel, {
            props: {
                pricingGate: {
                    status: 'blocked',
                    checks: [
                        {
                            key: 'template-selected',
                            label: 'Template Selected',
                            status: 'passed',
                            reason: 'The default Quick Generate template is visible as a read-only fact.',
                        },
                        {
                            key: 'pricing-service-wired',
                            label: 'Pricing Service Wired',
                            status: 'blocked',
                            reason: 'Cockpit does not call pricing services in Slice 20.',
                        },
                    ],
                    redactions: {
                        payloads: 'pricing-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Pricing Gate Baseline');
        expect(wrapper.text()).toContain('Template Selected');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Pricing Service Wired');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('Cockpit does not call pricing services in Slice 20.');
        expect(wrapper.text()).toContain('Pricing gates are read-only facts in Slice 20.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-pricing-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-pricing-gate-check"]')).toHaveLength(2);
    });

    it('renders funding gate facts without wallet access or reservation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateFundingGatePanel, {
            props: {
                fundingGate: {
                    status: 'blocked',
                    checks: [
                        {
                            key: 'funding-policy-known',
                            label: 'Funding Policy Known',
                            status: 'passed',
                            reason: 'Funding policy is represented as a read-only Cockpit readiness fact.',
                        },
                        {
                            key: 'issuer-wallet-identified',
                            label: 'Issuer Wallet Identified',
                            status: 'blocked',
                            reason: 'Cockpit does not resolve issuer wallets in Slice 21.',
                        },
                    ],
                    redactions: {
                        payloads: 'funding-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Funding Gate Baseline');
        expect(wrapper.text()).toContain('Funding Policy Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Issuer Wallet Identified');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('Cockpit does not resolve issuer wallets in Slice 21.');
        expect(wrapper.text()).toContain('Funding gates are read-only facts in Slice 21.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-funding-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-funding-gate-check"]')).toHaveLength(2);
    });

    it('renders idempotency gate facts without persistence or replay behavior', () => {
        const wrapper = mount(CockpitQuickGenerateIdempotencyGatePanel, {
            props: {
                idempotencyGate: {
                    status: 'blocked',
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
                    status: 'blocked',
                    checks: [
                        {
                            key: 'request-schema-known',
                            label: 'Request Schema Known',
                            status: 'passed',
                            reason: 'The Quick Generate draft contract schema is represented as a read-only Cockpit readiness fact.',
                        },
                        {
                            key: 'sensitive-fields-redacted',
                            label: 'Sensitive Fields Redacted',
                            status: 'blocked',
                            reason: 'Cockpit does not accept, persist, or redact submitted payloads in Slice 23.',
                        },
                    ],
                    redactions: {
                        payloads: 'validation-redaction-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Validation and Redaction Gate Baseline');
        expect(wrapper.text()).toContain('Request Schema Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Sensitive Fields Redacted');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('Cockpit does not accept, persist, or redact submitted payloads in Slice 23.');
        expect(wrapper.text()).toContain('Validation and redaction gates are read-only facts in Slice 23.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-validation-redaction-gate-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-validation-redaction-gate-check"]')).toHaveLength(2);
    });

    it('renders mutation handoff plan facts without mutation routes or generation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateMutationHandoffPlanPanel, {
            props: {
                mutationHandoffPlan: {
                    status: 'blocked',
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
                            status: 'blocked',
                            reason: 'Cockpit does not call GeneratePayCode in Slice 24.',
                        },
                    ],
                    redactions: {
                        payloads: 'mutation-handoff-plan-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Handoff Boundary Plan');
        expect(wrapper.text()).toContain('Existing Issuance Owner Identified');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('GeneratePayCode Action Handoff');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain('Cockpit does not call GeneratePayCode in Slice 24.');
        expect(wrapper.text()).toContain('Mutation handoff remains a read-only boundary plan in Slice 24.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-handoff-plan-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-mutation-handoff-plan-step"]')).toHaveLength(2);
    });

    it('renders mutation preconditions review facts without approving mutation wiring', () => {
        const wrapper = mount(CockpitQuickGenerateMutationPreconditionsReviewPanel, {
            props: {
                mutationPreconditionsReview: {
                    status: 'blocked',
                    recommendation: 'remain-read-only',
                    items: [
                        {
                            key: 'authorization-ready',
                            label: 'Authorization Ready',
                            status: 'blocked',
                            reason: 'Generation, provider, and money movement authorization gates remain blocked.',
                        },
                        {
                            key: 'handoff-ready',
                            label: 'Handoff Ready',
                            status: 'blocked',
                            reason: 'GeneratePayCode action handoff and GeneratePayCodeController handoff remain blocked.',
                        },
                    ],
                    redactions: {
                        payloads: 'mutation-preconditions-review-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Preconditions Review');
        expect(wrapper.text()).toContain('remain-read-only');
        expect(wrapper.text()).toContain('Authorization Ready');
        expect(wrapper.text()).toContain('Handoff Ready');
        expect(wrapper.text()).toContain('GeneratePayCode action handoff and GeneratePayCodeController handoff remain blocked.');
        expect(wrapper.text()).toContain('Mutation preconditions remain blocked in Slice 25.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-preconditions-review-panel"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="cockpit-quick-generate-mutation-preconditions-review-item"]')).toHaveLength(2);
    });

    it('renders the mutation authorization decision point without registering mutation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateMutationAuthorizationDecisionPanel, {
            props: {
                mutationAuthorizationDecision: {
                    status: 'blocked',
                    decision: 'not_authorized',
                    required_approval: 'human-approval-required-before-route-scaffold',
                    rationale: 'Mutation preconditions remain blocked; Cockpit must not register a write route until explicit human approval and a smaller mutation contract exist.',
                    next_step: 'request-explicit-approval-or-continue-read-only-hardening',
                    redactions: {
                        payloads: 'mutation-authorization-decision-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Authorization Decision Point');
        expect(wrapper.text()).toContain('not_authorized');
        expect(wrapper.text()).toContain('human-approval-required-before-route-scaffold');
        expect(wrapper.text()).toContain('explicit human approval');
        expect(wrapper.text()).toContain('request-explicit-approval-or-continue-read-only-hardening');
        expect(wrapper.text()).toContain('No Cockpit mutation route is authorized in Slice 26.');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-quick-generate-mutation-authorization-decision-panel"]').exists()).toBe(true);
    });

    it('renders the full Quick Generate page with active navigation and no side effects', () => {
        const wrapper = mount(QuickGenerate);

        expect(wrapper.find('[data-testid="cockpit-quick-generate-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Quick Generate Foundation');
        expect(wrapper.text()).toContain('Template Selector');
        expect(wrapper.text()).toContain('Runtime Inputs');
        expect(wrapper.text()).toContain('Pricing and Funding');
        expect(wrapper.text()).toContain('Generate Action');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Quick Generate');
        expect(wrapper.text()).toContain('does not generate vouchers');
        expect(wrapper.text()).toContain('calculate pricing');
        expect(wrapper.text()).toContain('reserve funds');
        expect(wrapper.text()).toContain('move money');
        expect(wrapper.text()).toContain('Issuance Boundary Plan');
        expect(wrapper.text()).toContain('No Cockpit mutation route is registered in Slice 17.');
        expect(wrapper.text()).toContain('Request Draft Contract');
        expect(wrapper.text()).toContain('Drafts are local and read-only in Slice 18.');
        expect(wrapper.text()).toContain('Authorization Gate Baseline');
        expect(wrapper.text()).toContain('Authorization gates are read-only facts in Slice 19.');
        expect(wrapper.text()).toContain('Pricing Gate Baseline');
        expect(wrapper.text()).toContain('Pricing gates are read-only facts in Slice 20.');
        expect(wrapper.text()).toContain('Funding Gate Baseline');
        expect(wrapper.text()).toContain('Funding gates are read-only facts in Slice 21.');
        expect(wrapper.text()).toContain('Idempotency Gate Baseline');
        expect(wrapper.text()).toContain('Idempotency gates are read-only facts in Slice 22.');
        expect(wrapper.text()).toContain('Validation and Redaction Gate Baseline');
        expect(wrapper.text()).toContain('Validation and redaction gates are read-only facts in Slice 23.');
        expect(wrapper.text()).toContain('Mutation Handoff Boundary Plan');
        expect(wrapper.text()).toContain('Mutation handoff remains a read-only boundary plan in Slice 24.');
        expect(wrapper.text()).toContain('Mutation Preconditions Review');
        expect(wrapper.text()).toContain('Mutation preconditions remain blocked in Slice 25.');
        expect(wrapper.text()).toContain('Mutation Authorization Decision Point');
        expect(wrapper.text()).toContain('No Cockpit mutation route is authorized in Slice 26.');
    });
});
