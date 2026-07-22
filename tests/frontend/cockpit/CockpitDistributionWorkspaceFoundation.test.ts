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

        expect(wrapper.text()).toContain('Notification channels');
        expect(wrapper.text()).toContain('Message and follow-up readiness');
        expect(wrapper.text()).toContain('Cockpit does not send notifications');
        expect(wrapper.text()).toContain('SMS handoff');
        expect(wrapper.text()).toContain('Email handoff');
        expect(wrapper.text()).toContain('In-app notification handoff');
        expect(wrapper.text()).toContain('Manual branch release');
        const panel = wrapper.find('[data-testid="cockpit-digital-distribution-panel"]');
        const channels = wrapper.findAll('[data-testid="cockpit-distribution-channel"]');

        expect(panel.element.tagName.toLowerCase()).toBe('details');
        expect(panel.classes()).toContain('py-3');
        expect(panel.classes()).not.toContain('p-5');
        expect(channels).toHaveLength(4);
        expect(channels[0].classes()).toContain('p-3');
        expect(wrapper.find('[data-testid="cockpit-distribution-density-summary"]').text()).toContain('Channels');
        expect(wrapper.find('[data-testid="cockpit-distribution-density-summary"]').text()).toContain('Disabled Follow-Ups');
        expect(wrapper.find('[data-testid="cockpit-distribution-density-summary"]').text()).toContain('4');

        const actions = wrapper.findAll('[data-testid="cockpit-distribution-action"]');

        expect(actions).toHaveLength(4);
        expect(wrapper.findAll('[data-testid="cockpit-distribution-action-row"]')).toHaveLength(4);
        expect(actions[0].classes()).toContain('py-1.5');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-action-disclosure"]')).toHaveLength(4);

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

        expect(wrapper.find('[data-testid="cockpit-print-template-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.text()).toContain('Printable handout options');
        expect(wrapper.text()).toContain('future handout ideas only');
        expect(wrapper.text()).toContain('Receipt card');
        expect(wrapper.text()).toContain('Branch release sheet');
        expect(wrapper.text()).toContain('Counter slip');
        expect(wrapper.text()).toContain('Print assets are not generated or persisted');
        expect(wrapper.find('[data-testid="cockpit-print-template-density-summary"]').text()).toContain('Templates');
        expect(wrapper.find('[data-testid="cockpit-print-template-density-summary"]').text()).toContain('3');
        expect(wrapper.findAll('[data-testid="cockpit-print-template"]')).toHaveLength(3);
        expect(wrapper.findAll('[data-testid="cockpit-print-template-disclosure"]')).toHaveLength(3);
    });

    it('renders share and QR placeholders without creating share assets', () => {
        const wrapper = mount(CockpitShareQrPanel, {
            props: {
                assets: cockpitShareAssets,
            },
        });

        expect(wrapper.find('[data-testid="cockpit-share-qr-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.text()).toContain('Copy, QR, and short-link readiness');
        expect(wrapper.text()).toContain('Only the claim URL can be copied today');
        expect(wrapper.text()).toContain('QR asset');
        expect(wrapper.text()).toContain('Short link');
        expect(wrapper.text()).toContain('Copy text');
        expect(wrapper.text()).toContain('QR generation must use an approved Pay Code representation');
        expect(wrapper.find('[data-testid="cockpit-share-asset-density-summary"]').text()).toContain('Assets');
        expect(wrapper.find('[data-testid="cockpit-share-asset-density-summary"]').text()).toContain('Deferred');
        expect(wrapper.findAll('[data-testid="cockpit-share-asset"]')).toHaveLength(3);
        expect(wrapper.findAll('[data-testid="cockpit-share-asset-disclosure"]')).toHaveLength(3);
    });

    it('renders operational distribution analytics without campaign ownership', () => {
        const wrapper = mount(CockpitDistributionAnalyticsPanel, {
            props: {
                metrics: cockpitDistributionMetrics,
            },
        });

        expect(wrapper.find('[data-testid="cockpit-distribution-analytics-panel"]').element.tagName.toLowerCase()).toBe('details');
        expect(wrapper.text()).toContain('Status evidence');
        expect(wrapper.text()).toContain('Delivery and campaign signals');
        expect(wrapper.text()).toContain('Open a row only if you need source details');
        expect(wrapper.text()).toContain('Planned sends');
        expect(wrapper.text()).toContain('Printed assets');
        expect(wrapper.text()).toContain('Delivery state');
        expect(wrapper.text()).toContain('Campaign state');
        expect(wrapper.text()).toContain('Campaign behavior is deferred until Wave 5');
        expect(wrapper.find('[data-testid="cockpit-distribution-analytics-density-summary"]').text()).toContain('Evidence Facts');
        expect(wrapper.find('[data-testid="cockpit-distribution-analytics-density-summary"]').text()).toContain('4 read-only facts');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-metric"]')).toHaveLength(4);
        expect(wrapper.findAll('[data-testid="cockpit-distribution-metric-disclosure"]')).toHaveLength(4);
    });

    it('renders the full distribution workspace page with Pay Codes navigation and side-effect boundaries', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Distribution inspection');
        expect(wrapper.text()).toContain('Distribution Workspace');
        expect(wrapper.text()).toContain('Inspect manual distribution readiness');
        expect(wrapper.text()).toContain('Manual next step');
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-summary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-detail-link"]').text()).toContain('Back to Pay Code Detail');
        expect(wrapper.find('[data-testid="cockpit-distribution-primary-explorer-link"]').text()).toContain('Back to Pay Codes');
        expect(wrapper.text()).toContain('Notification channels');
        expect(wrapper.text()).toContain('Print Templates');
        expect(wrapper.text()).toContain('Share options');
        expect(wrapper.text()).toContain('Status evidence');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.text()).toContain('Inspection only');
        expect(wrapper.text()).toContain('display and copy the claim URL');
        expect(wrapper.text()).toContain('cannot send messages');
        expect(wrapper.text()).toContain('change the Pay Code');
        expect(wrapper.text()).toContain('generate distribution assets');
        expect(wrapper.text()).toContain('move money');
    });

    it('renders the workspace shell as a sleek operational header', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
            },
        });

        const header = wrapper.find('[data-testid="cockpit-distribution-workspace-header"]');
        const headerRow = wrapper.find('[data-testid="cockpit-distribution-workspace-header-row"]');
        const facts = wrapper.find('[data-testid="cockpit-distribution-workspace-header-facts"]');
        const boundary = wrapper.find('[data-testid="cockpit-distribution-workspace-boundary"]');

        expect(header.exists()).toBe(true);
        expect(header.classes()).toContain('py-3');
        expect(header.classes()).not.toContain('p-4');
        expect(headerRow.classes()).toContain('lg:items-center');
        expect(header.text()).toContain('Distribution Workspace');
        expect(header.text()).toContain('Inspect manual distribution readiness and beneficiary URL availability.');
        expect(header.text()).toContain('read-only');
        expect(facts.findAll('[data-testid="cockpit-distribution-workspace-header-fact"]')).toHaveLength(3);
        expect(facts.classes()).toContain('lg:flex-1');
        expect(facts.classes()).toContain('p-1.5');
        expect(facts.find('[data-testid="cockpit-distribution-workspace-header-fact"]').classes()).toContain('py-1.5');
        expect(boundary.element.tagName.toLowerCase()).toBe('details');
        expect(boundary.classes()).toContain('mt-2');
        expect(boundary.classes()).toContain('pt-2');
        expect(boundary.find('summary').text()).toContain('Read-only limits');
        expect(boundary.text()).toContain('Inspection only');
        expect(boundary.text()).toContain('display and copy the claim URL');
        expect(boundary.text()).toContain('cannot send messages');
        expect(boundary.text()).toContain('change the Pay Code');
        expect(boundary.text()).toContain('generate distribution assets');
        expect(boundary.text()).toContain('move money');
        expect(boundary.text()).not.toContain('mutate vouchers');
        expect(boundary.text()).not.toContain('execute drivers');
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
                    key: 'delivery-sms-1',
                    label: 'SMS',
                    status: 'delivered',
                    description: 'x-feedback delivery state is shown for operator inspection only.',
                    read_only: true,
                    available: true,
                    source: 'x-feedback',
                    metadata: {
                        provider_status: 'DELIVERED',
                        attempt_count: 2,
                        max_attempts: 3,
                        communication_state_only: true,
                        sends_feedback: false,
                        retries_delivery: false,
                    },
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
                    key: 'ERN-COCKPIT-DISTRIBUTION-JOURNAL-001',
                    label: 'Journal: distribution.audit.recorded',
                    status: 'available',
                    description: 'Distribution audit guidance from x-journal. · 2026-07-19T10:00:00Z · journal-evidence-summary-only',
                    read_only: true,
                    available: true,
                    source: 'x-journal',
                    metadata: {
                        event_type: 'distribution.audit.recorded',
                        payload_policy: 'journal-evidence-summary-only',
                        evidence_only: true,
                        writes_journal: false,
                    },
                },
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
                    key: 'distribution.manual-review',
                    label: 'Review Manual Distribution',
                    status: 'available',
                    description: 'Inspect manual distribution readiness before sending externally.',
                    read_only: true,
                    available: true,
                    source: 'x-action',
                    metadata: {
                        target_route: 'x-change.cockpit.pay-codes.distribution',
                        target_type: 'route',
                        presentation_run: true,
                        executes_action: false,
                    },
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

        expect(wrapper.text()).toContain('Distribution inspection');
        expect(wrapper.text()).toContain('Distribution Workspace');
        expect(wrapper.text()).toContain('PC-DIST-001');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.text()).toContain('distribution-read-model-summary-only');
        expect(wrapper.text()).toContain('Beneficiary Pay Code URL');
        expect(wrapper.text()).toContain('https://example.test/x/claim/PC-DIST-001/experience');
        expect(wrapper.text()).toContain('/x/claim/PC-DIST-001/experience');
        expect(wrapper.text()).toContain('delivery disabled');
        expect(wrapper.text()).toContain('Connected context');
        expect(wrapper.text()).toContain('inspection only');
        expect(wrapper.text()).toContain('Claim URL');
        expect(wrapper.text()).toContain('Ready');
        expect(wrapper.text()).toContain('Delivery Evidence');
        expect(wrapper.text()).toContain('Follow-Up Guidance');
        expect(wrapper.text()).toContain('Audit Evidence');
        const connectedContext = wrapper.find('[data-testid="cockpit-distribution-connected-context-summary"]');

        expect(connectedContext.exists()).toBe(true);
        expect(connectedContext.element.tagName.toLowerCase()).toBe('details');
        expect(connectedContext.attributes('open')).toBeUndefined();
        expect(connectedContext.find('summary').text()).toContain('Connected context');
        expect(connectedContext.find('summary').text()).toContain('4 read-only facts');
        expect(connectedContext.find('summary').text()).toContain('inspection only');
        expect(connectedContext.findAll('[data-testid="cockpit-distribution-connected-context-item"]')).toHaveLength(4);
        expect(connectedContext.find('[data-testid="cockpit-distribution-connected-context-item"]').classes()).toContain('py-2');
        const claimLinkDetails = wrapper.find('[data-testid="cockpit-distribution-workspace-links-panel"]');

        expect(claimLinkDetails.exists()).toBe(true);
        expect(claimLinkDetails.element.tagName.toLowerCase()).toBe('details');
        expect(claimLinkDetails.attributes('open')).toBeUndefined();
        expect(claimLinkDetails.find('summary').text()).toContain('Beneficiary Pay Code URL');
        expect(claimLinkDetails.find('summary').text()).toContain('URL details');
        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-beneficiary-url-link"]').attributes('href')).toBe('https://example.test/x/claim/PC-DIST-001/experience');
        expect(wrapper.text()).toContain('Copy text');
        expect(wrapper.text()).toContain('preview');
        expect(wrapper.text()).toContain('voucher-summary');
        expect(wrapper.text()).toContain('QR asset');
        expect(wrapper.text()).toContain('deferred');
        expect(wrapper.text()).toContain('SMS');
        expect(wrapper.text()).toContain('delivered');
        expect(wrapper.text()).toContain('x-feedback');
        expect(wrapper.text()).toContain('Provider Status');
        expect(wrapper.text()).toContain('DELIVERED');
        expect(wrapper.text()).toContain('Attempts');
        expect(wrapper.text()).toContain('2/3');
        expect(wrapper.text()).toContain('Communication State Only');
        expect(wrapper.text()).toContain('true');
        expect(wrapper.text()).toContain('Receipt card');
        expect(wrapper.text()).toContain('Delivery state');
        expect(wrapper.text()).toContain('Journal: distribution.audit.recorded');
        expect(wrapper.text()).toContain('Distribution audit guidance from x-journal');
        expect(wrapper.text()).toContain('Event Type');
        expect(wrapper.text()).toContain('Payload Policy');
        expect(wrapper.text()).toContain('Evidence Only');
        expect(wrapper.text()).toContain('Writes Journal');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).toContain('Review Manual Distribution');
        expect(wrapper.text()).toContain('available');
        expect(wrapper.text()).toContain('Target Route');
        expect(wrapper.text()).toContain('x-change.cockpit.pay-codes.distribution');
        expect(wrapper.text()).toContain('Target Type');
        expect(wrapper.text()).toContain('route');
        expect(wrapper.text()).toContain('Presentation Run');
        expect(wrapper.text()).toContain('true');
        expect(wrapper.text()).toContain('Executes Action');
        expect(wrapper.text()).toContain('false');
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

    it('compresses primary readiness around the manual next step', () => {
        const wrapper = mount(DistributionWorkspace, {
            props: {
                context: { code: 'PC-DIST-001' },
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-distribution-primary-summary"]');
        const readinessStrip = wrapper.find('[data-testid="cockpit-distribution-primary-readiness-strip"]');
        const readinessItems = readinessStrip.findAll('[data-testid="cockpit-distribution-primary-readiness-item"]');
        const checklist = wrapper.find('[data-testid="cockpit-distribution-manual-checklist"]');

        expect(summary.classes()).toContain('p-4');
        expect(summary.text()).toContain('delivery remains external');
        expect(readinessStrip.classes()).toContain('p-2');
        expect(readinessItems).toHaveLength(4);
        expect(readinessItems[0].classes()).toContain('py-2');
        expect(checklist.element.tagName.toLowerCase()).toBe('details');
        expect(checklist.find('summary').text()).toContain('Manual distribution checklist');
        expect(checklist.find('summary').text()).toContain('5 steps');
        expect(checklist.findAll('[data-testid="cockpit-distribution-manual-checklist-item"]')).toHaveLength(5);
    });

    it('guides operators to detailed readiness panels without repeating readiness cards', () => {
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

        const readiness = wrapper.find('[data-testid="cockpit-distribution-readiness-panel-guide"]');

        expect(readiness.exists()).toBe(true);
        expect(readiness.element.tagName.toLowerCase()).toBe('details');
        expect(readiness.attributes('open')).toBeUndefined();
        expect(readiness.text()).toContain('Detailed readiness panels');
        expect(readiness.text()).toContain('details below');
        expect(readiness.text()).toContain('Notification, print, evidence, and share details are grouped below');
        expect(readiness.text()).toContain('cannot send messages');
        expect(wrapper.find('[data-testid="cockpit-distribution-channel-artifact-readiness"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="cockpit-distribution-channel-artifact-readiness-item"]')).toHaveLength(0);
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
        expect(checklist.element.tagName.toLowerCase()).toBe('details');
        expect(checklist.text()).toContain('Manual distribution checklist');
        expect(checklist.find('summary').text()).toContain('5 steps');
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
        expect(wrapper.text()).toContain('Distribution Workspace');
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
        expect(wrapper.text()).toContain('campaign-wave-46');
        expect(wrapper.text()).toContain('audience-wave-46');
        expect(wrapper.text()).toContain('recipient-wave-46');
        expect(wrapper.text()).toContain('Campaign package adapter');
        expect(wrapper.text()).toContain('Distribution Workspace');
        expect(wrapper.text()).toContain('Campaign navigation only');
        expect(wrapper.text()).toContain('Navigation context only');
        expect(wrapper.text()).not.toContain('x_campaign_adapter');
        expect(wrapper.text()).not.toContain('distribution_workspace');
        expect(wrapper.text()).not.toContain('campaign-navigation-read-only');
        expect(wrapper.text()).not.toContain('navigation-context-only');
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
