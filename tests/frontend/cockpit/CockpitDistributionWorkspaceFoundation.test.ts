import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
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
    afterEach(() => {
        vi.unstubAllGlobals();
    });

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
            distribution_links: {
                schema: 'x-change.cockpit.distribution-links.v1',
                status: 'available',
                available: true,
                read_only: true,
                redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                redeem_path: '/x/claim/PC-DIST-001/experience',
                source: 'x-change.claim.experience',
                delivery_enabled: false,
                redactions: {
                    payloads: 'distribution-links-only',
                    secret_claim_material_exposed: false,
                    provider_payloads_exposed: false,
                    wallet_data_exposed: false,
                    delivery_payloads_exposed: false,
                },
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
        expect(wrapper.text()).toContain('Beneficiary Pay Code URL');
        expect(wrapper.text()).toContain('https://example.test/x/claim/PC-DIST-001/experience');
        expect(wrapper.text()).toContain('/x/claim/PC-DIST-001/experience');
        expect(wrapper.text()).toContain('delivery disabled');
        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-links-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-beneficiary-url-link"]').attributes('href')).toBe('https://example.test/x/claim/PC-DIST-001/experience');
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

    it('renders Distribution Workspace manual distribution operational guidance', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-001',
                    summary: {
                        code: 'PC-DIST-001',
                        display_status: 'ready',
                    },
                    distribution_links: {
                        schema: 'x-change.cockpit.distribution-links.v1',
                        status: 'available',
                        available: true,
                        read_only: true,
                        redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                        redeem_path: '/x/claim/PC-DIST-001/experience',
                        source: 'x-change.claim.experience',
                        delivery_enabled: false,
                        redactions: { payloads: 'distribution-links-only' },
                    },
                    redactions: {
                        payloads: 'distribution-read-model-summary-only',
                    },
                },
            },
        });

        const guidance = wrapper.find('[data-testid="cockpit-distribution-workspace-manual-distribution-guidance"]');

        expect(guidance.exists()).toBe(true);
        expect(guidance.text()).toContain('Manual distribution guidance');
        expect(guidance.text()).toContain('manual distribution only');
        expect(guidance.text()).toContain('approved external workflow');
        expect(guidance.text()).toContain('verifying the recipient');
        expect(guidance.text()).toContain('does not send SMS, email, webhook, in-app notification, or campaign delivery');
        expect(guidance.text()).toContain('does not record copy telemetry');
        expect(guidance.text()).toContain('sensitive settlement access material');
    });

    it('renders a primary manual distribution summary with safe actions', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-001',
                    summary: {
                        code: 'PC-DIST-001',
                        display_status: 'ready',
                    },
                    distribution_links: {
                        schema: 'x-change.cockpit.distribution-links.v1',
                        status: 'available',
                        available: true,
                        read_only: true,
                        redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                        redeem_path: '/x/claim/PC-DIST-001/experience',
                        source: 'x-change.claim.experience',
                        delivery_enabled: false,
                        redactions: { payloads: 'distribution-links-only' },
                    },
                    redactions: {
                        payloads: 'distribution-read-model-summary-only',
                    },
                },
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-distribution-primary-summary"]');
        const readiness = summary.findAll('[data-testid="cockpit-distribution-primary-readiness-item"]');

        expect(summary.exists()).toBe(true);
        expect(summary.text()).toContain('Manual distribution summary');
        expect(summary.text()).toContain('Pay Code PC-DIST-001');
        expect(summary.text()).toContain('Claim URL');
        expect(summary.text()).toContain('ready');
        expect(summary.text()).toContain('Delivery');
        expect(summary.text()).toContain('disabled');
        expect(summary.text()).toContain('Artifacts');
        expect(summary.text()).toContain('deferred');
        expect(summary.text()).toContain('Payload Policy');
        expect(summary.text()).toContain('distribution-read-model-summary-only');
        expect(summary.text()).toContain('Copy or inspect the beneficiary claim URL');
        expect(summary.text()).toContain('does not deliver messages');
        expect(readiness).toHaveLength(4);
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-claim-url-link"]').attributes('href')).toBe('https://example.test/x/claim/PC-DIST-001/experience');
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-detail-link"]').attributes('href')).toBe('/x/cockpit/pay-codes/PC-DIST-001');
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-explorer-link"]').attributes('href')).toBe('/x/cockpit/pay-codes');
    });

    it('summarizes channel and artifact readiness without dispatch controls', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-001',
                    summary: {
                        code: 'PC-DIST-001',
                        display_status: 'ready',
                    },
                    distribution_links: {
                        schema: 'x-change.cockpit.distribution-links.v1',
                        status: 'available',
                        available: true,
                        read_only: true,
                        redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                        redeem_path: '/x/claim/PC-DIST-001/experience',
                        source: 'x-change.claim.experience',
                        delivery_enabled: false,
                        redactions: { payloads: 'distribution-links-only' },
                    },
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
                    share_assets: [
                        {
                            key: 'copy-text',
                            label: 'Copy text',
                            status: 'preview',
                            description: 'Operator-safe Pay Code copy text can be displayed.',
                            read_only: true,
                            available: true,
                            source: 'voucher-summary',
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
                    redactions: {
                        payloads: 'distribution-read-model-summary-only',
                    },
                },
            },
        });

        const readiness = wrapper.find('[data-testid="cockpit-distribution-channel-artifact-readiness"]');
        const items = readiness.findAll('[data-testid="cockpit-distribution-channel-artifact-readiness-item"]');

        expect(readiness.exists()).toBe(true);
        expect(readiness.text()).toContain('Channel and artifact readiness');
        expect(readiness.text()).toContain('no dispatch');
        expect(readiness.text()).toContain('Channels');
        expect(readiness.text()).toContain('1 planned');
        expect(readiness.text()).toContain('Operator Actions');
        expect(readiness.text()).toContain('1 blocked');
        expect(readiness.text()).toContain('Print Assets');
        expect(readiness.text()).toContain('1 preview');
        expect(readiness.text()).toContain('Share Assets');
        expect(readiness.text()).toContain('2 display');
        expect(readiness.text()).toContain('does not send messages');
        expect(items).toHaveLength(4);
    });

    it('renders a manual distribution checklist near the primary summary', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-001',
                    summary: {
                        code: 'PC-DIST-001',
                        display_status: 'ready',
                    },
                    distribution_links: {
                        schema: 'x-change.cockpit.distribution-links.v1',
                        status: 'available',
                        available: true,
                        read_only: true,
                        redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                        redeem_path: '/x/claim/PC-DIST-001/experience',
                        source: 'x-change.claim.experience',
                        delivery_enabled: false,
                        redactions: { payloads: 'distribution-links-only' },
                    },
                    redactions: {
                        payloads: 'distribution-read-model-summary-only',
                    },
                },
            },
        });

        const checklist = wrapper.find('[data-testid="cockpit-distribution-manual-checklist"]');
        const items = checklist.findAll('[data-testid="cockpit-distribution-manual-checklist-item"]');

        expect(checklist.exists()).toBe(true);
        expect(checklist.text()).toContain('Manual distribution checklist');
        expect(checklist.text()).toContain('Verify the intended recipient outside Cockpit');
        expect(checklist.text()).toContain('Copy the beneficiary claim URL from this page');
        expect(checklist.text()).toContain('approved external workflow');
        expect(checklist.text()).toContain('Do not treat copy as delivery confirmation');
        expect(checklist.text()).toContain('Return to Pay Code Detail');
        expect(items).toHaveLength(5);
    });

    it('copies the Distribution Workspace beneficiary URL through the browser clipboard only', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        });
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
                distribution_workspace_read_model: {
                    schema: 'x-change.cockpit.distribution-workspace.v1',
                    status: 'available',
                    authorized: true,
                    code: 'PC-DIST-001',
                    summary: {
                        code: 'PC-DIST-001',
                        display_status: 'ready',
                    },
                    distribution_links: {
                        schema: 'x-change.cockpit.distribution-links.v1',
                        status: 'available',
                        available: true,
                        read_only: true,
                        redeem_url: 'https://example.test/x/claim/PC-DIST-001/experience',
                        redeem_path: '/x/claim/PC-DIST-001/experience',
                        source: 'x-change.claim.experience',
                        delivery_enabled: false,
                        redactions: { payloads: 'distribution-links-only' },
                    },
                    redactions: {
                        payloads: 'distribution-read-model-summary-only',
                    },
                },
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-DIST-001/experience');
        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').text()).toContain('Copied');
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('No delivery was sent');
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
        expect(wrapper.text()).toContain('Campaign context');
        expect(wrapper.text()).toContain('Inspecting distribution from campaign activity');
        expect(wrapper.text()).toContain('only move between read-only Cockpit views');
        expect(wrapper.text()).toContain('plan-wave-46');
        expect(wrapper.text()).toContain('exec-wave-46');
        expect(wrapper.text()).toContain('recipient-wave-46');
        expect(wrapper.text()).toContain('distribution_workspace');
        expect(wrapper.text()).toContain('campaign-navigation-read-only');
        expect(wrapper.text()).toContain('navigation-context-only');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-detail-return-link"]').attributes('href')).toBe('/x/cockpit/pay-codes/Not%20wired?campaign_planning_key=plan-wave-46&campaign_execution_id=exec-wave-46&campaign_id=campaign-wave-46&campaign_audience_id=audience-wave-46&campaign_recipient_id=recipient-wave-46&campaign_source=x_campaign_adapter');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-detail-return-link"]').text()).toContain('Back to Pay Code Detail');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-detail-return-link"]').text()).toContain('read-only');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-explorer-return-link"]').attributes('href')).toBe('/x/cockpit/pay-codes?campaign_planning_key=plan-wave-46&campaign_execution_id=exec-wave-46&campaign_id=campaign-wave-46&campaign_audience_id=audience-wave-46&campaign_recipient_id=recipient-wave-46&campaign_source=x_campaign_adapter&activity_code=Not+wired&activity_source=operator_issuance_activity');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-explorer-return-link"]').text()).toContain('Back to Explorer');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-explorer-return-link"]').text()).toContain('read-only');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-dashboard-return-link"]').attributes('href')).toBe('/x/cockpit?campaign_planning_key=plan-wave-46&campaign_execution_id=exec-wave-46&campaign_id=campaign-wave-46&campaign_audience_id=audience-wave-46&campaign_recipient_id=recipient-wave-46&campaign_source=x_campaign_adapter');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-dashboard-return-link"]').text()).toContain('Back to Campaign Dashboard');
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-dashboard-return-link"]').text()).toContain('read-only');
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
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-detail-return-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-explorer-return-link"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="cockpit-distribution-campaign-dashboard-return-link"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('plan-wave-46');
    });
});
