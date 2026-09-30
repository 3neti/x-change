import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import CockpitOnDemandIssuanceFundingDialog from '../../../resources/js/cockpit/components/CockpitOnDemandIssuanceFundingDialog.vue';
import type { CockpitOnDemandIssuanceFundingProjection } from '../../../resources/js/cockpit/types';

const projection: CockpitOnDemandIssuanceFundingProjection = {
    schema: 'x-change.cockpit.on-demand-issuance-funding.v1',
    status: 'awaiting_payment',
    funding_required: true,
    lifecycle: {
        current: 'awaiting_payment',
        verification_unavailable: false,
        message:
            'Transfer the exact amount, then ask x-change to check the payment.',
        steps: [
            {
                key: 'awaiting_payment',
                label: 'Awaiting payment',
                state: 'current',
            },
            {
                key: 'checking_payment',
                label: 'Checking payment',
                state: 'pending',
            },
            {
                key: 'payment_verified',
                label: 'Payment verified',
                state: 'pending',
            },
            {
                key: 'issuing_pay_code',
                label: 'Issuing Pay Code',
                state: 'pending',
            },
            {
                key: 'pay_code_ready',
                label: 'Pay Code ready',
                state: 'pending',
            },
        ],
    },
    actions: {
        show: '/x/cockpit/quick-generate/funding-orders/ORDER-1',
        acknowledge:
            '/x/cockpit/quick-generate/funding-orders/ORDER-1/acknowledge',
        verify: '/x/cockpit/quick-generate/funding-orders/ORDER-1/verification',
        cancel: '/x/cockpit/quick-generate/funding-orders/ORDER-1',
    },
    monitor: {
        enabled: true,
        eligible: true,
        interval_milliseconds: 10_000,
        last_checked_at: null,
    },
    order: {
        reference: 'ORDER-1',
        funding_basis: 'full_amount',
        required_amount_minor: 5_000,
        reserved_client_funds_minor: 0,
        on_demand_amount_minor: 5_000,
        reconciliation_adjustment_minor: 0,
        expected_payment_minor: 5_000,
        currency: 'PHP',
        status: 'awaiting_payment',
        expires_at: '2026-09-29T09:00:00+08:00',
        can_cancel: true,
        late_payment_disposition: null,
        late_payment_detected_at: null,
        voucher: null,
        receipt: {
            order_reference: 'ORDER-1',
            expected_payment_minor: 5_000,
            currency: 'PHP',
            provider_transaction_id: null,
            verified_at: null,
            settled_at: null,
            issued_at: null,
        },
    },
    funding_selector: {
        schema: 'x-change.cockpit.funding-method-selector.v1',
        context: 'pay_code_issuance',
        intent_reference: 'INTENT-1',
        order_reference: 'ORDER-1',
        amount: {
            currency: 'PHP',
            principal_minor: 5_000,
            fee_minor: 0,
            required_minor: 5_000,
            principal: '50.00',
            fee: '0.00',
            required: '50.00',
        },
        expires_at: '2026-09-29T09:00:00+08:00',
        status: 'awaiting_payment',
        default_mode: 'bank_transfer',
        notice: 'Keep this window open.',
        methods: [
            {
                key: 'bank_transfer',
                workspace_mode: 'bank_transfer',
                label: 'Bank Transfer',
                description: 'Transfer the exact amount.',
                available: true,
                selectable: true,
                unavailable_reason: null,
            },
            {
                key: 'qr_ph',
                workspace_mode: 'self_top_up',
                label: 'QR Ph',
                description: 'Fixed amount QR.',
                available: false,
                selectable: false,
                unavailable_reason: 'Fixed-amount QR unavailable.',
            },
            {
                key: 'pay_code',
                workspace_mode: 'pay_code',
                label: 'Pay Code',
                description: 'Not enabled.',
                available: false,
                selectable: false,
                unavailable_reason: 'Not enabled.',
            },
        ],
        bank_transfer: {
            instructions: {
                institution: 'NetBank',
                account_name: '3neti R&D OPC',
                funding_address: '113-001-00001-9',
            },
            reconciliation_reference: {
                mode: 'disabled',
                value: null,
                label: 'Transfer reference',
                instructions: 'Exact amount matching.',
            },
            matching_strategies: [
                'reserved_exact_amount',
                'destination_account',
                'currency',
                'observation_window',
            ],
        },
        qr_ph: {
            fixed_amount: false,
            amount_minor: null,
            currency: null,
            image: null,
            qr_mode: null,
            transaction_type: null,
            provider_generated: false,
            notice: null,
        },
    },
};

describe('CockpitOnDemandIssuanceFundingDialog', () => {
    it('keeps bank transfer primary and presents an exact read-only amount', () => {
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain(
            'Pay ₱50.00 to issue this Pay Code',
        );
        expect(document.body.textContent).toContain('Exact transfer amount');
        expect(document.body.textContent).toContain('113-001-00001-9');
        expect(document.body.textContent).toContain(
            'Payment checking is automatic',
        );
        expect(document.body.textContent).toContain('Pay only once');
        expect(
            document.body.querySelector(
                '[data-testid="on-demand-payment-check"]',
            ),
        ).not.toBeNull();
        expect(document.body.querySelector('[aria-label="Close"]')).toBeNull();

        wrapper.unmount();
    });

    it('automatically requests an idempotent provider check while the order is open', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValue({
                ok: true,
                json: async () => structuredClone(projection),
            } as Response);
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection },
            attachTo: document.body,
        });

        await vi.advanceTimersByTimeAsync(10_000);

        expect(fetchMock).toHaveBeenNthCalledWith(
            1,
            projection.actions.show,
            expect.objectContaining({ credentials: 'same-origin' }),
        );
        expect(fetchMock).toHaveBeenNthCalledWith(
            2,
            projection.actions.verify,
            expect.objectContaining({ method: 'POST' }),
        );

        wrapper.unmount();
        fetchMock.mockRestore();
        vi.useRealTimers();
    });

    it('fails closed when funding instructions need operator attention', () => {
        const attentionProjection = structuredClone(projection);
        attentionProjection.status = 'issuance_attention';
        attentionProjection.order.status = 'issuance_attention';
        attentionProjection.order.can_cancel = true;
        attentionProjection.lifecycle.current = 'attention';
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: attentionProjection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain('Funding needs attention');
        expect(document.body.textContent).toContain(
            'No payment should be made',
        );
        expect(document.body.textContent).not.toContain(
            'I’ve made the transfer',
        );
        expect(document.body.textContent).toContain('Cancel safely');

        wrapper.unmount();
    });

    it('keeps an active verification workspace open until it reaches an outcome', () => {
        const checkingProjection = structuredClone(projection);
        checkingProjection.status = 'payer_acknowledged';
        checkingProjection.order.status = 'payer_acknowledged';
        checkingProjection.lifecycle.current = 'checking_payment';
        checkingProjection.lifecycle.message =
            'Payment is not visible yet. You do not need to pay again.';
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: checkingProjection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain(
            'Payment is not visible yet',
        );
        expect(document.body.textContent).toContain('Check now');
        expect(document.body.querySelector('[aria-label="Close"]')).toBeNull();
        expect(wrapper.emitted('cancelled')).toBeUndefined();

        wrapper.unmount();
    });

    it('states provider outages without implying nonpayment', () => {
        const unavailableProjection = structuredClone(projection);
        unavailableProjection.status = 'payer_acknowledged';
        unavailableProjection.order.status = 'payer_acknowledged';
        unavailableProjection.lifecycle.current = 'checking_payment';
        unavailableProjection.lifecycle.verification_unavailable = true;
        unavailableProjection.lifecycle.message =
            'Verification is temporarily unavailable. You do not need to pay again.';
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: unavailableProjection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain(
            'Verification is temporarily unavailable',
        );
        expect(document.body.textContent).toContain(
            'You do not need to pay again',
        );

        wrapper.unmount();
    });

    it('renders an order-specific exact-amount QR Ph when the provider proves it', async () => {
        const fixedQrProjection = structuredClone(projection);
        fixedQrProjection.funding_selector.methods[1].available = true;
        fixedQrProjection.funding_selector.methods[1].selectable = true;
        fixedQrProjection.funding_selector.methods[1].unavailable_reason = null;
        fixedQrProjection.funding_selector.qr_ph = {
            fixed_amount: true,
            amount_minor: 5_000,
            currency: 'PHP',
            image: 'data:image/png;base64,ZmFrZQ==',
            qr_mode: 'dynamic',
            transaction_type: 'p2m',
            provider_generated: true,
            notice: 'The exact amount is encoded in this order-specific QR.',
        };
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: fixedQrProjection },
            attachTo: document.body,
        });

        document
            .querySelector<HTMLButtonElement>(
                '[data-testid="funding-mode-self_top_up"]',
            )
            ?.click();
        await nextTick();

        expect(
            document.body.querySelector(
                '[data-testid="on-demand-fixed-qr-ph"]',
            ),
        ).not.toBeNull();
        expect(document.body.textContent).toContain('Exact QR Ph amount');
        expect(document.body.textContent).toContain('Check now');
        expect(document.body.querySelector('img')?.getAttribute('src')).toBe(
            'data:image/png;base64,ZmFrZQ==',
        );

        wrapper.unmount();
    });

    it('replaces payment controls with a calm late-payment outcome after expiry', () => {
        const expiredProjection = structuredClone(projection);
        expiredProjection.status = 'expired';
        expiredProjection.order.status = 'expired';
        expiredProjection.order.can_cancel = false;
        expiredProjection.order.late_payment_disposition = 'client_funds';
        expiredProjection.order.late_payment_detected_at =
            '2026-09-29T09:01:00+08:00';
        expiredProjection.monitor.eligible = false;
        expiredProjection.lifecycle.current = 'expired';
        expiredProjection.lifecycle.message =
            'Your payment arrived after this order expired and was added to Client Funds. No Pay Code was issued.';
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: expiredProjection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain(
            'Payment added to Client Funds',
        );
        expect(document.body.textContent).toContain('Your money is safe');
        expect(document.body.textContent).not.toContain('Check now');
        expect(
            document.body.querySelector(
                '[data-testid="on-demand-fixed-qr-ph"]',
            ),
        ).toBeNull();
        expect(
            document.body.querySelector('[aria-label="Close"]'),
        ).not.toBeNull();

        wrapper.unmount();
    });
});
