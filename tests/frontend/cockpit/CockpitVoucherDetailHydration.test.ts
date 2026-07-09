import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
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
    it('hydrates voucher detail from sanitized read model summary', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                context: { code: 'PC-HYDRATED-001' },
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('Voucher Detail Read Model');
        expect(wrapper.text()).toContain('PC-HYDRATED-001');
        expect(wrapper.text()).toContain('ready');
        expect(wrapper.text()).toContain('₱1,500.75');
        expect(wrapper.text()).toContain('Not claimed');
        expect(wrapper.text()).toContain('sanitized-summary-only');
        expect(wrapper.findAll('[data-testid="cockpit-voucher-overview-item"]').length).toBeGreaterThanOrEqual(6);
    });

    it('keeps dependent read models explicitly not wired during voucher hydration', () => {
        const wrapper = mount(VoucherDetail, {
            props: {
                read_model: readModel,
            },
        });

        expect(wrapper.text()).toContain('Execution read model');
        expect(wrapper.text()).toContain('Journal read model');
        expect(wrapper.text()).toContain('Action handoff');
        expect(wrapper.text()).toContain('Feedback delivery');
        expect(wrapper.text()).toContain('not_wired');
        expect(wrapper.text()).toContain('No execution driver is invoked by this screen.');
        expect(wrapper.text()).toContain('Journal entries remain unavailable until an authorized journal read model is wired.');
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
        expect(wrapper.text()).toContain('Action execution remains disabled from Cockpit.');
        expect(wrapper.text()).toContain('safe-action-host-summary-only');
        expect(wrapper.text()).not.toContain('/must-not-render');
        expect(wrapper.text()).not.toContain('raw_diagnostics');
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
