import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDigitalDistributionPanel from '../../../resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue';
import CockpitDistributionAnalyticsPanel from '../../../resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue';
import CockpitPrintTemplatePanel from '../../../resources/js/cockpit/components/CockpitPrintTemplatePanel.vue';
import CockpitShareQrPanel from '../../../resources/js/cockpit/components/CockpitShareQrPanel.vue';
import DistributionWorkspace from '../../../resources/js/cockpit/pages/DistributionWorkspace.vue';
import DistributionWorkspaceRouteAdapter from '../../../resources/js/pages/x-change/cockpit/DistributionWorkspace.vue';
import {
    cockpitDistributionActions,
    cockpitDistributionChannels,
    cockpitDistributionMetrics,
    cockpitPrintTemplates,
    cockpitShareAssets,
} from '../../../resources/js/cockpit/distributionWorkspaceDefaults';

describe('Cockpit Distribution Workspace foundation', () => {
    it('renders digital distribution planning placeholders with disabled actions', () => {
        const wrapper = mount(CockpitDigitalDistributionPanel, {
            props: {
                channels: cockpitDistributionChannels,
                actions: cockpitDistributionActions,
            },
        });

        expect(wrapper.text()).toContain('Channel planning placeholder');
        expect(wrapper.text()).toContain('SMS handoff');
        expect(wrapper.text()).toContain('Email handoff');
        expect(wrapper.text()).toContain('In-app notification handoff');
        expect(wrapper.text()).toContain('Manual branch release');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-channel"]')).toHaveLength(4);

        const actions = wrapper.findAll('[data-testid="cockpit-distribution-action"]');

        expect(actions).toHaveLength(4);

        for (const action of actions) {
            expect(action.attributes('disabled')).toBeDefined();
            expect(action.attributes('title')).toBeTruthy();
        }
    });

    it('renders print template placeholders without generating artifacts', () => {
        const wrapper = mount(CockpitPrintTemplatePanel, {
            props: {
                templates: cockpitPrintTemplates,
            },
        });

        expect(wrapper.text()).toContain('Print template placeholder');
        expect(wrapper.text()).toContain('Receipt card');
        expect(wrapper.text()).toContain('Branch release sheet');
        expect(wrapper.text()).toContain('Counter slip');
        expect(wrapper.text()).toContain('Print assets are not generated or persisted');
        expect(wrapper.findAll('[data-testid="cockpit-print-template"]')).toHaveLength(3);
    });

    it('renders share and QR placeholders without creating share assets', () => {
        const wrapper = mount(CockpitShareQrPanel, {
            props: {
                assets: cockpitShareAssets,
            },
        });

        expect(wrapper.text()).toContain('Share asset placeholder');
        expect(wrapper.text()).toContain('QR asset');
        expect(wrapper.text()).toContain('Short link');
        expect(wrapper.text()).toContain('Copy text');
        expect(wrapper.text()).toContain('QR generation must use an approved Pay Code representation');
        expect(wrapper.findAll('[data-testid="cockpit-share-asset"]')).toHaveLength(3);
    });

    it('renders operational distribution analytics without campaign ownership', () => {
        const wrapper = mount(CockpitDistributionAnalyticsPanel, {
            props: {
                metrics: cockpitDistributionMetrics,
            },
        });

        expect(wrapper.text()).toContain('Distribution analytics placeholder');
        expect(wrapper.text()).toContain('Planned sends');
        expect(wrapper.text()).toContain('Printed assets');
        expect(wrapper.text()).toContain('Delivery state');
        expect(wrapper.text()).toContain('Campaign state');
        expect(wrapper.text()).toContain('Campaign behavior is deferred until Wave 5');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-metric"]')).toHaveLength(4);
    });

    it('renders the full distribution workspace page with Pay Codes navigation and side-effect boundaries', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Distribution Workspace Foundation');
        expect(wrapper.text()).toContain('Digital Distribution');
        expect(wrapper.text()).toContain('Print Templates');
        expect(wrapper.text()).toContain('Share / QR');
        expect(wrapper.text()).toContain('Operational Analytics');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.text()).toContain('does not dispatch distribution');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('create campaigns');
        expect(wrapper.text()).toContain('mutate vouchers');
        expect(wrapper.text()).toContain('execute drivers');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('call providers');
        expect(wrapper.text()).toContain('move money');
    });

    it('renders hydrated read-only distribution workspace facts from route props', () => {
        const distributionWorkspaceReadModel = {
            schema: 'x-change.cockpit.distribution-workspace.v1',
            status: 'available',
            authorized: true,
            code: 'PC-DIST-001',
            summary: {
                code: 'PC-DIST-001',
                display_status: 'ready',
                provider_payload: 'must-not-render',
                raw_payload: 'must-not-render',
                wallet: 'must-not-render',
            },
            share_assets: [
                {
                    key: 'copy-text',
                    label: 'Copy text',
                    status: 'preview',
                    description: 'Operator-safe Pay Code copy text can be displayed without secret claim material.',
                    read_only: true,
                    available: true,
                    source: 'voucher-summary',
                    metadata: {
                        copies_secret_claim_material: false,
                    },
                },
                {
                    key: 'qr',
                    label: 'QR asset',
                    status: 'deferred',
                    description: 'QR generation remains disabled.',
                    read_only: true,
                    available: false,
                    source: 'distribution-policy',
                },
            ],
            channels: [
                {
                    key: 'sms',
                    label: 'SMS',
                    status: 'not_wired',
                    description: 'SMS delivery state must come from x-feedback.',
                    read_only: true,
                    available: false,
                    source: 'feedback-read-model',
                },
            ],
            print_templates: [
                {
                    key: 'receipt-card',
                    label: 'Receipt card',
                    status: 'planned',
                    description: 'Receipt card output remains preview-only.',
                    read_only: true,
                    available: false,
                    source: 'distribution-policy',
                },
            ],
            analytics: [
                {
                    key: 'delivery-state',
                    label: 'Delivery state',
                    status: 'not_wired',
                    description: 'Delivery truth is communication state from x-feedback.',
                    read_only: true,
                    available: false,
                    source: 'feedback-read-model',
                },
            ],
            actions: [
                {
                    key: 'send-now',
                    label: 'Send now',
                    status: 'blocked',
                    description: 'Distribution dispatch is not authorized from Cockpit.',
                    read_only: true,
                    available: false,
                    source: 'mutation-boundary',
                },
            ],
            redactions: {
                payloads: 'distribution-read-model-summary-only',
                dispatch_enabled: false,
                artifact_generation_enabled: false,
                campaign_mutation_enabled: false,
            },
        };

        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: distributionWorkspaceReadModel,
            },
        });

        expect(wrapper.text()).toContain('Wave 33 · Share surface');
        expect(wrapper.text()).toContain('Distribution Workspace Runtime');
        expect(wrapper.text()).toContain('PC-DIST-001');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.text()).toContain('distribution-read-model-summary-only');
        expect(wrapper.text()).toContain('Copy text');
        expect(wrapper.text()).toContain('preview');
        expect(wrapper.text()).toContain('voucher-summary');
        expect(wrapper.text()).toContain('QR asset');
        expect(wrapper.text()).toContain('deferred');
        expect(wrapper.text()).toContain('SMS');
        expect(wrapper.text()).toContain('feedback-read-model');
        expect(wrapper.text()).toContain('Receipt card');
        expect(wrapper.text()).toContain('Delivery state');
        expect(wrapper.text()).toContain('Send now');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
    });

    it('forwards route adapter props into the distribution workspace page', () => {
        const wrapper = mount(DistributionWorkspaceRouteAdapter, {
            props: {
                context: { code: 'PC-DIST-002' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-002',
                    summary: { code: 'PC-DIST-002', display_status: 'ready' },
                    share_assets: [],
                    channels: [],
                    print_templates: [],
                    analytics: [],
                    actions: [],
                    redactions: { payloads: 'distribution-read-model-summary-only' },
                },
            },
        });

        expect(wrapper.text()).toContain('PC-DIST-002');
        expect(wrapper.text()).toContain('Distribution Workspace Runtime');
        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-shell"]').exists()).toBe(true);
    });

    it('renders safe campaign recipient context on distribution workspace without dispatch controls', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                campaign_navigation_context: {
                    schema: 'x-change.cockpit.campaign-navigation.v1',
                    status: 'available',
                    authorized: true,
                    source: 'x_campaign_adapter',
                    planning_key: 'plan-wave-46',
                    execution_id: 'exec-wave-46',
                    campaign_id: 'campaign-wave-46',
                    audience_id: 'audience-wave-46',
                    recipient_id: 'recipient-wave-46',
                    destination: 'distribution_workspace',
                    read_only: true,
                    mutation: {
                        enabled: false,
                        status: 'blocked',
                        reason: 'campaign-navigation-read-only',
                    },
                    redactions: {
                        payloads: 'navigation-context-only',
                    },
                    provider_payload: 'must-not-render',
                    raw_payload: 'must-not-render',
                    wallet: 'must-not-render',
                    mutation_route: '/must-not-render',
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-navigation-context"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Campaign recipient context');
        expect(wrapper.text()).toContain('Read-only Distribution context');
        expect(wrapper.text()).toContain('plan-wave-46');
        expect(wrapper.text()).toContain('exec-wave-46');
        expect(wrapper.text()).toContain('recipient-wave-46');
        expect(wrapper.text()).toContain('distribution_workspace');
        expect(wrapper.text()).toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
        expect(wrapper.text()).not.toContain('/must-not-render');
    });

    it('does not render campaign context on distribution workspace for the wrong destination', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                campaign_navigation_context: {
                    status: 'available',
                    authorized: true,
                    planning_key: 'plan-wave-46',
                    execution_id: 'exec-wave-46',
                    destination: 'pay_code_detail',
                    read_only: true,
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-navigation-context"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('plan-wave-46');
    });
});
