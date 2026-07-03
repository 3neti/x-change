import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitGenerateActionPanel from '../../../resources/js/cockpit/components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../../../resources/js/cockpit/components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../../../resources/js/cockpit/components/CockpitPricingFundingSummary.vue';
import CockpitQuickGenerateAuthorizationGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateAuthorizationGatePanel.vue';
import CockpitQuickGenerateDraftContractPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateDraftContractPanel.vue';
import CockpitRuntimeInputPanel from '../../../resources/js/cockpit/components/CockpitRuntimeInputPanel.vue';
import CockpitTemplateSelector from '../../../resources/js/cockpit/components/CockpitTemplateSelector.vue';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';
import {
    cockpitPricingFundingSummary,
    cockpitQuickGenerateTemplates,
    cockpitRuntimeInputs,
} from '../../../resources/js/cockpit/quickGenerateDefaults';

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
    });
});
