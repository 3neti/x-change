import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import VoucherDetail from '../../../resources/js/cockpit/pages/VoucherDetail.vue';
import VoucherDetailRouteAdapter from '../../../resources/js/pages/x-change/cockpit/VoucherDetail.vue';

const readModel = {
    code: 'PC-HYDRATED-001',
    voucher: {
        code: 'PC-HYDRATED-001',
        status: 'issued',
        summary: {
            code: 'PC-HYDRATED-001',
            status: 'issued',
            display_status: 'ready',
            amount: 1500.75,
            currency: 'PHP',
            claimed: false,
            fully_claimed: false,
            created_at: '2026-07-03T10:00:00+08:00',
            starts_at: '2026-07-03T11:00:00+08:00',
            expires_at: '2026-07-10T11:00:00+08:00',
            redeemed_at: null,
            provider_payload: 'must-not-render',
            raw_payload: 'must-not-render',
            wallet: 'must-not-render',
            provider: 'must-not-render',
            instructions: 'must-not-render',
            claims: 'must-not-render',
            approval: 'must-not-render',
        },
        evidence_summary: [
            {
                key: 'lifecycle',
                label: 'Lifecycle facts',
                status: 'ready',
                description: 'Sanitized voucher lifecycle summary is available.',
                read_only: true,
                available: true,
                source: 'voucher',
            },
            {
                key: 'claim',
                label: 'Claim evidence',
                status: 'not_claimed',
                description: 'Claim state is represented only as sanitized summary booleans.',
                read_only: true,
                available: true,
                source: 'voucher-summary',
            },
            {
                key: 'approval',
                label: 'Approval evidence',
                status: 'redacted',
                description: 'Approval references and OTP metadata remain redacted from the voucher summary.',
                read_only: true,
                available: false,
                source: 'redaction-policy',
            },
            {
                key: 'execution',
                label: 'Execution evidence',
                status: 'not_wired',
                description: 'Execution evidence is read from the execution read model; this page does not invoke drivers.',
                read_only: true,
                available: false,
                source: 'execution-read-model',
            },
            {
                key: 'journal',
                label: 'Journal evidence',
                status: 'not_wired',
                description: 'Journal evidence is read-only and only available through authorized journal read models.',
                read_only: true,
                available: false,
                source: 'journal-read-model',
            },
            {
                key: 'actions',
                label: 'Action evidence',
                status: 'not_wired',
                description: 'Action evidence is presentation-only; Cockpit does not execute actions from Voucher Detail.',
                read_only: true,
                available: false,
                source: 'action-read-model',
            },
            {
                key: 'feedback',
                label: 'Feedback evidence',
                status: 'not_wired',
                description: 'Feedback evidence is communication state only; Cockpit does not send feedback from Voucher Detail.',
                read_only: true,
                available: false,
                source: 'feedback-read-model',
            },
        ],
        distribution_links: {
            schema: 'x-change.cockpit.distribution-links.v1',
            status: 'available',
            available: true,
            read_only: true,
            redeem_url: 'https://example.test/x/claim/PC-HYDRATED-001/experience',
            redeem_path: '/x/claim/PC-HYDRATED-001/experience',
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
        redactions: {
            payloads: 'sanitized-summary-only',
            excluded: [
                'instructions',
                'claims',
                'approval',
                'provider_payload',
                'raw_payload',
                'wallet',
                'provider',
            ],
        },
        authorized: true,
    },
    execution: {
        execution_id: null,
        status: 'not_wired',
        driver: null,
        events: [],
        metadata: [],
        redactions: { payloads: 'not-loaded' },
        authorized: false,
    },
    journal: {
        status: 'not_wired',
        entries: [],
        redactions: { payloads: 'not-loaded' },
        authorized: false,
    },
    actions: {
        status: 'not_wired',
        actions: [],
        diagnostics: [],
        redactions: { payloads: 'not-loaded' },
        authorized: false,
    },
    feedback: {
        status: 'not_wired',
        deliveries: [],
        redactions: { payloads: 'not-loaded' },
        authorized: false,
    },
};

describe('Cockpit Voucher Detail hydration', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('hydrates voucher detail from sanitized read model summary', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Code Detail');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.text()).toContain('Not claimed');
        expect(wrapper.text()).toContain('sanitized-summary-only');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-overview-item"]').length).toBeGreaterThanOrEqual(6);
    });

    it('renders hydrated voucher evidence summary facts without exposing unsafe payloads', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('Evidence summary');
        expect(wrapper.text()).toContain('Lifecycle facts');
        expect(wrapper.text()).toContain('Claim evidence');
        expect(wrapper.text()).toContain('Approval evidence');
        expect(wrapper.text()).toContain('Execution evidence');
        expect(wrapper.text()).toContain('Journal evidence');
        expect(wrapper.text()).toContain('Action evidence');
        expect(wrapper.text()).toContain('Feedback evidence');
        expect(wrapper.text()).toContain('voucher-summary');
        expect(wrapper.text()).toContain('redaction-policy');
        expect(wrapper.text()).toContain('Read-only');
        expect(wrapper.text()).toContain('yes');
        expect(wrapper.find('[data-testid="cockpit-voucher-evidence-density-summary"]').text()).toContain('Evidence Facts');
        expect(wrapper.find('[data-testid="cockpit-voucher-evidence-density-summary"]').text()).toContain('7');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-evidence-item-metadata"]')).toHaveLength(7);
        expect(wrapper.findAll('[data-testid="cockpit-voucher-evidence-item"]')).toHaveLength(7);
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('renders a primary operator summary with safe next-step actions', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-voucher-detail-primary-summary"]');
        const readinessStrip = wrapper.find('[data-testid="cockpit-voucher-detail-primary-readiness-strip"]');
        const readinessItems = readinessStrip.findAll('[data-testid="cockpit-voucher-detail-primary-readiness-item"]');
        const claimUrl = wrapper.find('[data-testid="cockpit-voucher-detail-primary-claim-url-link"]');
        const distribution = wrapper.find('[data-testid="cockpit-voucher-detail-primary-distribution-link"]');
        const explorer = wrapper.find('[data-testid="cockpit-voucher-detail-primary-explorer-link"]');

        expect(summary.exists()).toBe(true);
        expect(summary.classes()).toContain('p-4');
        expect(summary.classes()).not.toContain('p-5');
        expect(readinessStrip.classes()).toContain('p-2');
        expect(readinessItems).toHaveLength(4);
        expect(readinessItems[0].classes()).toContain('py-2');
        expect(summary.text()).toContain('Operator detail summary');
        expect(summary.text()).toContain('Pay Code PC-HYDRATED-001');
        expect(summary.text()).toContain('ready');
        expect(summary.text()).toContain('₱1,500.75');
        expect(summary.text()).toContain('Not claimed');
        expect(summary.text()).toContain('Claim URL');
        expect(summary.text()).toContain('Manual copy/inspection only');
        expect(summary.text()).toContain('Copy or inspect the beneficiary claim URL');
        expect(summary.text()).toContain('sanitized-summary-only');
        expect(summary.text()).toContain('without mutating the Pay Code or triggering delivery');
        expect(claimUrl.attributes('href')).toBe('https://example.test/x/claim/PC-HYDRATED-001/experience');
        expect(distribution.attributes('href')).toBe('/x/cockpit/pay-codes/PC-HYDRATED-001/distribution');
        expect(explorer.attributes('href')).toBe('/x/cockpit/pay-codes');
        expect(summary.text()).not.toContain('provider_payload');
        expect(summary.text()).not.toContain('raw_payload');
        expect(summary.text()).not.toContain('must-not-render');
    });

    it('copies the primary summary claim URL through the browser clipboard only', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        });
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const summary = wrapper.find('[data-testid="cockpit-voucher-detail-primary-summary"]');
        const buttons = summary.findAll('[data-testid="cockpit-manual-copy-button"]');

        expect(buttons).toHaveLength(1);
        expect(buttons[0].text()).toContain('Copy claim URL');

        await buttons[0].trigger('click');

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-HYDRATED-001/experience');
        expect(fetch).not.toHaveBeenCalled();
    });

    it('summarizes read-only evidence readiness in the primary detail area', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const readiness = wrapper.find('[data-testid="cockpit-voucher-detail-primary-evidence-readiness"]');
        const items = readiness.findAll('[data-testid="cockpit-voucher-detail-primary-evidence-readiness-item"]');

        expect(readiness.exists()).toBe(true);
        expect(readiness.element.tagName.toLowerCase()).toBe('details');
        expect(readiness.attributes('open')).toBeUndefined();
        expect(readiness.find('summary').text()).toContain('3 service summaries');
        expect(readiness.text()).toContain('Connected services');
        expect(readiness.text()).toContain('Read-only audit, follow-up, and notification state');
        expect(readiness.text()).toContain('summary only');
        expect(readiness.text()).toContain('Audit');
        expect(readiness.text()).toContain('Follow-Up');
        expect(readiness.text()).toContain('Notifications');
        expect(readiness.text()).toContain('Not connected');
        expect(readiness.text()).toContain('No data loaded');
        expect(readiness.text()).toContain('0 entries');
        expect(readiness.text()).toContain('0 actions');
        expect(readiness.text()).toContain('0 deliveries');
        expect(items).toHaveLength(3);
    });

    it('renders a scan-friendly connected context summary in the primary detail area', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }, { id: 'journal-2' }],
                        redactions: { payloads: 'journal-evidence-summary-only' },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: { payloads: 'safe-action-host-summary-only' },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: { payloads: 'communication-delivery-summary-only' },
                    },
                },
            },
        });

        const context = wrapper.find('[data-testid="cockpit-voucher-detail-connected-context"]');
        const items = context.findAll('[data-testid="cockpit-voucher-detail-connected-context-item"]');

        expect(context.exists()).toBe(true);
        expect(context.element.tagName.toLowerCase()).toBe('details');
        expect(context.attributes('open')).toBeUndefined();
        expect(context.find('summary').text()).toContain('4 read-only facts');
        expect(context.text()).toContain('Connected context');
        expect(context.text()).toContain('claim access, notification state, follow-up guidance, and audit evidence');
        expect(context.text()).toContain('Claim URL');
        expect(context.text()).toContain('Ready');
        expect(context.text()).toContain('Full URL');
        expect(context.text()).toContain('Delivery Evidence');
        expect(context.text()).toContain('1 deliveries');
        expect(context.text()).toContain('Follow-Up Guidance');
        expect(context.text()).toContain('1 actions');
        expect(context.text()).toContain('Audit Evidence');
        expect(context.text()).toContain('2 entries');
        expect(context.text()).not.toContain('provider_payload');
        expect(context.text()).not.toContain('raw_payload');
        expect(items).toHaveLength(4);
        expect(items[0].classes()).toContain('py-2');
    });

    it('renders lifecycle guidance from display status without enforcing policy', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const guidance = wrapper.find('[data-testid="cockpit-voucher-detail-lifecycle-guidance"]');

        expect(guidance.exists()).toBe(true);
        expect(guidance.element.tagName.toLowerCase()).toBe('details');
        expect(guidance.attributes('open')).toBeUndefined();
        expect(guidance.text()).toContain('Lifecycle guidance');
        expect(guidance.text()).toContain('Available');
        expect(guidance.text()).toContain('ready');
        expect(guidance.text()).toContain('sanitized lifecycle summary');
        expect(guidance.text()).toContain('does not enforce lifecycle policy');
    });

    it('renders expired lifecycle guidance as a read-only warning', () => {
        const expiredReadModel = {
            ...readModel,
            voucher: {
                ...readModel.voucher,
                status: 'expired',
                summary: {
                    ...readModel.voucher.summary,
                    status: 'expired',
                    display_status: 'expired',
                },
            },
        };

        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: expiredReadModel,
            },
        });

        const guidance = wrapper.find('[data-testid="cockpit-voucher-detail-lifecycle-guidance"]');

        expect(guidance.text()).toContain('Expired');
        expect(guidance.text()).toContain('warning');
        expect(guidance.text()).toContain('Review evidence before manual distribution');
        expect(guidance.text()).toContain('does not enforce lifecycle policy');
    });

    it('renders the beneficiary Pay Code URL as a read-only distribution link', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const panel = wrapper.find('[data-testid="cockpit-voucher-detail-distribution-links-panel"]');
        const link = wrapper.find('[data-testid="cockpit-voucher-detail-beneficiary-url-link"]');

        expect(panel.exists()).toBe(true);
        expect(panel.text()).toContain('Beneficiary Pay Code URL');
        expect(panel.text()).toContain('https://example.test/x/claim/PC-HYDRATED-001/experience');
        expect(panel.text()).toContain('/x/claim/PC-HYDRATED-001/experience');
        expect(panel.text()).toContain('read-only');
        expect(panel.text()).toContain('delivery disabled');
        expect(panel.text()).toContain('distribution-links-only');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-distribution-link-density-summary"]').text()).toContain('Claim URL');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-distribution-link-density-summary"]').text()).toContain('Ready');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-distribution-link-density-summary"]').text()).toContain('Browser-local');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-distribution-link-metadata"]').exists()).toBe(true);
        expect(link.attributes('href')).toBe('https://example.test/x/claim/PC-HYDRATED-001/experience');
    });

    it('renders Voucher Detail manual distribution operational guidance', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        const guidance = wrapper.find('[data-testid="cockpit-voucher-detail-manual-distribution-guidance"]');

        expect(guidance.exists()).toBe(true);
        expect(guidance.element.tagName).toBe('DETAILS');
        expect(guidance.text()).toContain('Manual distribution guidance');
        expect(guidance.text()).toContain('manual distribution only');
        expect(guidance.text()).toContain('approved external workflow');
        expect(guidance.text()).toContain('verifying the recipient');
        expect(guidance.text()).toContain('does not send SMS, email, webhook, in-app notification, or campaign delivery');
        expect(guidance.text()).toContain('does not record copy telemetry');
        expect(guidance.text()).toContain('sensitive settlement access material');
    });

    it('copies the Voucher Detail beneficiary URL through the browser clipboard only', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText,
            },
        });
        vi.stubGlobal('fetch', vi.fn());

        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        await wrapper.find('[data-testid="cockpit-manual-copy-button"]').trigger('click');
        await Promise.resolve();

        expect(writeText).toHaveBeenCalledWith('https://example.test/x/claim/PC-HYDRATED-001/experience');
        expect(globalThis.fetch).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="cockpit-manual-copy-button"]').text()).toContain('Copied');
        expect(wrapper.find('[data-testid="cockpit-manual-copy-status"]').text()).toContain('No delivery was sent');
    });

    it('keeps dependent read models explicitly not wired during voucher hydration', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('Execution read model');
        expect(wrapper.text()).toContain('Audit trail');
        expect(wrapper.text()).toContain('Follow-up guidance');
        expect(wrapper.text()).toContain('Notification status');
        expect(wrapper.text()).toContain('not_wired');
        expect(wrapper.text()).toContain('No execution driver is invoked by this screen.');
        expect(wrapper.text()).toContain('Audit entries remain unavailable until an authorized journal read model is connected.');
    });

    it('hydrates journal evidence entries without rendering unsafe journal payloads', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [
                            {
                                id: 'journal-entry-1',
                                event_type: 'voucher.redeemed',
                                summary: 'Voucher redemption recorded',
                                occurred_at: '2026-07-03T12:00:00+08:00',
                                provider_payload: 'must-not-render',
                                raw_payload: 'must-not-render',
                                secret: 'must-not-render',
                            },
                        ],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            source: 'x-journal',
                            evidence_only: true,
                            writes_journal_entries: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Journal: voucher.redeemed');
        expect(wrapper.text()).toContain('Voucher redemption recorded');
        expect(wrapper.text()).toContain('2026-07-03T12:00:00+08:00');
        expect(wrapper.text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
    });

    it('renders real x-journal payload summaries in the audit panel', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [
                            {
                                reference_number: 'ERN-COCKPIT-VOUCHER-EVIDENCE-001',
                                event_type: 'voucher.audit.recorded',
                                occurred_at: '2026-07-19T10:00:00+08:00',
                                payload: {
                                    summary: 'Voucher evidence summary from x-journal.',
                                    raw_payload: '[redacted]',
                                    provider_payload: '[redacted]',
                                },
                                metadata: {
                                    wallet: '[redacted]',
                                },
                            },
                        ],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            source: 'x-journal',
                            evidence_only: true,
                            writes_journal_entries: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').text()).toContain('Journal: voucher.audit.recorded');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').text()).toContain('Voucher evidence summary from x-journal.');
        expect(wrapper.find('[data-testid="cockpit-voucher-audit-panel"]').text()).toContain('journal-evidence-summary-only');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
    });

    it('hydrates action read-model CTAs as disabled operator actions without unsafe payloads', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [
                            {
                                key: 'approve-redemption',
                                label: 'Approve redemption',
                                status: 'available',
                                description: 'Review the redemption evidence.',
                                target: {
                                    type: 'route',
                                    route: 'x-change.cockpit.pay-codes.distribution',
                                },
                                target_url: '/must-not-render',
                                raw_diagnostics: 'must-not-render',
                                provider_payload: 'must-not-render',
                            },
                        ],
                        diagnostics: [
                            {
                                code: 'operator-eligible',
                                message: 'Operator may view this CTA.',
                                raw_payload: 'must-not-render',
                            },
                        ],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                            source: 'x-action',
                            presentation_only: true,
                            executes_action: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Approve redemption');
        expect(wrapper.text()).toContain('Audit and follow-up details');
        expect(wrapper.text()).toContain('Follow-up actions are disabled from this page.');
        expect(wrapper.text()).toContain('Review the redemption evidence.');
        expect(wrapper.text()).toContain('Target: x-change.cockpit.pay-codes.distribution');
        expect(wrapper.text()).toContain('Follow-up action is disabled; Cockpit does not execute x-action actions from Voucher Detail.');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).not.toContain('/must-not-render');
        expect(wrapper.text()).not.toContain('raw_diagnostics');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('hydrates feedback delivery summaries without enabling delivery or exposing payloads', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [
                            {
                                delivery_id: 'delivery-1',
                                channel: 'sms',
                                status: 'delivered',
                                attempt_count: 1,
                                max_attempts: 3,
                                provider_status: 'ACCEPTED',
                                recipient: '+639170000000',
                                provider_payload: 'must-not-render',
                                raw_payload: 'must-not-render',
                                secret: 'must-not-render',
                            },
                        ],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                            source: 'x-feedback',
                            sends_feedback: false,
                            calls_providers: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('SMS');
        expect(wrapper.text()).toContain('delivered');
        expect(wrapper.text()).toContain('Provider status: ACCEPTED');
        expect(wrapper.text()).toContain('Attempts: 1/3');
        expect(wrapper.text()).toContain('communication-delivery-summary-only');
        expect(wrapper.text()).toContain('Notification delivery remains read-only from Cockpit.');
        expect(wrapper.text()).not.toContain('+639170000000');
        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
    });

    it('renders a voucher-level integration summary across journal actions and feedback', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [{ id: 'journal-1' }, { id: 'journal-2' }],
                        redactions: { payloads: 'journal-evidence-summary-only' },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [{ key: 'review' }],
                        redactions: { payloads: 'safe-action-host-summary-only' },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [{ id: 'delivery-1' }],
                        redactions: { payloads: 'communication-delivery-summary-only' },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Audit, follow-up, and notification status');
        expect(wrapper.text()).toContain('2 entries');
        expect(wrapper.text()).toContain('1 actions');
        expect(wrapper.text()).toContain('1 deliveries');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-integration-summary-card"]')).toHaveLength(3);
    });

    it('renders voucher integration unavailable reasons without exception messages', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'unavailable',
                        authorized: false,
                        entries: [],
                        redactions: {
                            payloads: 'not-loaded',
                            reason: 'read-model-unavailable',
                            exception: 'RuntimeException',
                            exception_message: 'must-not-render',
                            exception_message_exposed: false,
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Audit, follow-up, and notification status');
        expect(wrapper.text()).toContain('Read model unavailable');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('must-not-render');
    });

    it('does not render unsafe voucher payload fields from the read model summary', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: readModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
        expect(wrapper.text()).not.toContain('wallet');
        expect(wrapper.text()).not.toContain('instructions');
        expect(wrapper.text()).not.toContain('approval-reference');
    });

    it('keeps voucher operator integration summaries authorization and redaction safe', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                can: {
                    view_cockpit: true,
                    mutate_vouchers: false,
                    execute_drivers: false,
                    write_journal_entries: false,
                    send_feedback: false,
                    call_providers: false,
                    move_money: false,
                },
                read_model: {
                    ...readModel,
                    journal: {
                        status: 'available',
                        authorized: true,
                        entries: [
                            {
                                id: 'journal-1',
                                event_type: 'voucher.redeemed',
                                raw_payload: 'SECRET-DO-NOT-RENDER',
                                exception_message: 'Stack trace must stay hidden',
                            },
                        ],
                        redactions: {
                            payloads: 'journal-evidence-summary-only',
                            reason: 'read-model-ready',
                            exception: 'RuntimeException',
                            exception_message: 'Stack trace must stay hidden',
                            internal_route: '/unsafe-journal-route',
                        },
                    },
                    actions: {
                        status: 'available',
                        authorized: true,
                        actions: [
                            {
                                key: 'approve-redemption',
                                label: 'Approve redemption',
                                status: 'available',
                                target_url: '/unsafe-action-route',
                                run_id: 'non-durable-run-id',
                                raw_diagnostics: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        diagnostics: [
                            {
                                code: 'operator-eligible',
                                message: 'Operator may view this CTA.',
                                raw_payload: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'safe-action-host-summary-only',
                            reason: 'presentation-only',
                            executes_action: false,
                        },
                    },
                    feedback: {
                        status: 'available',
                        authorized: true,
                        deliveries: [
                            {
                                id: 'delivery-1',
                                channel: 'sms',
                                status: 'delivered',
                                recipient: '+639170000000',
                                provider_payload: 'SECRET-DO-NOT-RENDER',
                            },
                        ],
                        redactions: {
                            payloads: 'communication-delivery-summary-only',
                            reason: 'read-model-ready',
                            credential: 'SECRET-DO-NOT-RENDER',
                        },
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Audit, follow-up, and notification status');
        expect(wrapper.text()).toContain('Audit summary only');
        expect(wrapper.text()).toContain('Follow-up summary only');
        expect(wrapper.text()).toContain('Notification summary only');
        expect(wrapper.text()).not.toContain('SECRET-DO-NOT-RENDER');
        expect(wrapper.text()).not.toContain('RuntimeException');
        expect(wrapper.text()).not.toContain('Stack trace must stay hidden');
        expect(wrapper.text()).not.toContain('/unsafe-journal-route');
        expect(wrapper.text()).not.toContain('/unsafe-action-route');
        expect(wrapper.text()).not.toContain('non-durable-run-id');
        expect(wrapper.text()).not.toContain('+639170000000');
    });

    it('forwards Inertia route adapter props into the Cockpit voucher detail page', () => {
        const wrapper = mount(VoucherDetailRouteAdapter, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.find('[data-testid="cockpit-voucher-detail-shell"]').exists()).toBe(true);
    });
});
