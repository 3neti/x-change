import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import CockpitOnDemandIssuanceFundingDialog from '../../../resources/js/cockpit/components/CockpitOnDemandIssuanceFundingDialog.vue';
import type { CockpitOnDemandIssuanceFundingProjection } from '../../../resources/js/cockpit/types';

const projection: CockpitOnDemandIssuanceFundingProjection = {
    schema: 'x-change.cockpit.on-demand-issuance-funding.v1',
    status: 'awaiting_payment',
    funding_required: true,
    actions: {
        show: '/x/cockpit/quick-generate/funding-orders/ORDER-1',
        acknowledge:
            '/x/cockpit/quick-generate/funding-orders/ORDER-1/acknowledge',
        cancel: '/x/cockpit/quick-generate/funding-orders/ORDER-1',
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
        voucher: null,
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

        expect(document.body.textContent).toContain('Pay ₱50.00 to issue this Pay Code');
        expect(document.body.textContent).toContain('Exact transfer amount');
        expect(document.body.textContent).toContain('113-001-00001-9');
        expect(
            document.body.querySelector('[data-testid="on-demand-payment-check"]'),
        ).not.toBeNull();
        expect(document.body.querySelector('[aria-label="Close"]')).toBeNull();

        wrapper.unmount();
    });

    it('fails closed when funding instructions need operator attention', () => {
        const attentionProjection = structuredClone(projection);
        attentionProjection.status = 'issuance_attention';
        attentionProjection.order.status = 'issuance_attention';
        attentionProjection.order.can_cancel = true;
        const wrapper = mount(CockpitOnDemandIssuanceFundingDialog, {
            props: { open: true, projection: attentionProjection },
            attachTo: document.body,
        });

        expect(document.body.textContent).toContain('Funding needs attention');
        expect(document.body.textContent).toContain('No payment should be made');
        expect(document.body.textContent).not.toContain('I’ve made the transfer');
        expect(document.body.textContent).toContain('Cancel safely');

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
            .querySelector<HTMLButtonElement>('[data-testid="funding-mode-self_top_up"]')
            ?.click();
        await nextTick();

        expect(document.body.querySelector('[data-testid="on-demand-fixed-qr-ph"]')).not.toBeNull();
        expect(document.body.textContent).toContain('Exact QR Ph amount');
        expect(document.body.textContent).toContain('I’ve paid by QR Ph — Check payment');
        expect(document.body.querySelector('img')?.getAttribute('src')).toBe('data:image/png;base64,ZmFrZQ==');

        wrapper.unmount();
    });
});
