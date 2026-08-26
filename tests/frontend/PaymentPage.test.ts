import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PaymentPage from '../../resources/js/pages/x-change/claim/Payment.vue';

const { routerPost } = vi.hoisted(() => ({
    routerPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    router: {
        post: routerPost,
    },
}));

const pendingPayment = {
    pay_code: 'PAY-1234',
    currency: 'PHP',
    target_amount_minor: 10000,
    collected_amount_minor: 2500,
    amount_due_minor: 7500,
    is_fully_paid: false,
    rider_message: 'Transportation payment for the August field visit.',
    provider: 'netbank',
    provider_available: true,
    can_create_attempt: true,
    attempt: null,
    receipt: null,
};

describe('PaymentPage', () => {
    beforeEach(() => {
        routerPost.mockReset();
    });

    it('starts one exact payment attempt through the generated route', async () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: pendingPayment,
            },
        });

        expect(wrapper.text()).toContain('₱75.00');
        expect(wrapper.text()).toContain(
            'Creating instructions does not mark this Pay Code paid.',
        );

        expect(wrapper.get('[data-testid="payer-xray-step"]').text()).toContain(
            'Transportation payment for the August field visit.',
        );
        expect(
            wrapper.get('[data-testid="payer-invoice-step"]').text(),
        ).toContain('₱100.00');
        expect(
            wrapper.get('[data-testid="payer-invoice-step"]').text(),
        ).toContain('₱25.00');
        expect(
            wrapper.find('[data-testid="payer-print-receipt"]').exists(),
        ).toBe(false);

        await wrapper.get('[data-testid="payer-method-qr"]').trigger('click');
        await wrapper.get('[data-testid="payer-create-qr"]').trigger('click');

        expect(routerPost).toHaveBeenCalledWith(
            '/x/pay/PAY-1234/attempts',
            {},
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
    });

    it('renders the session-bound QR and exact amount', () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: {
                    ...pendingPayment,
                    attempt: {
                        reference: '01JTEST',
                        status: 'awaiting_payment',
                        provider: 'netbank',
                        amount_minor: 7500,
                        currency: 'PHP',
                        expires_at: '2026-07-24T10:15:00+08:00',
                        last_checked_at: null,
                        can_check: true,
                        qr_code: {
                            mime_type: 'image/png',
                            base64_payload: 'iVBORw0KGgo=',
                            qr_mode: 'dynamic',
                            transaction_type: 'p2m',
                            embedded_amount: true,
                        },
                    },
                },
            },
        });

        expect(wrapper.get('img').attributes('src')).toBe(
            'data:image/png;base64,iVBORw0KGgo=',
        );
        expect(wrapper.text()).toContain('Pay exactly ₱75.00');
        expect(wrapper.text()).toContain('cannot fund your x-change Account');
        expect(wrapper.text()).toContain('Check payment status');
    });

    it('checks authoritative NetBank history for the current attempt', async () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: {
                    ...pendingPayment,
                    attempt: {
                        reference: '01JTEST',
                        status: 'awaiting_payment',
                        provider: 'netbank',
                        amount_minor: 7500,
                        currency: 'PHP',
                        expires_at: '2026-07-24T10:15:00+08:00',
                        last_checked_at: null,
                        can_check: true,
                        qr_code: null,
                    },
                },
            },
        });

        await wrapper
            .get('[data-testid="payer-check-status"]')
            .trigger('click');

        expect(routerPost).toHaveBeenCalledWith(
            '/x/pay/PAY-1234/attempts/01JTEST/checks',
            {},
            expect.objectContaining({
                preserveScroll: true,
                replace: true,
            }),
        );
    });

    it('does not expose payment creation when the provider is unavailable', () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: {
                    ...pendingPayment,
                    provider_available: false,
                    can_create_attempt: false,
                },
            },
        });

        expect(
            wrapper
                .get('[data-testid="payer-method-qr"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.text()).toContain(
            'QR Code payment is not available in this environment.',
        );
    });

    it('shows unavailable methods without issuing requests', async () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: pendingPayment,
            },
        });

        const bank = wrapper.get('[data-testid="payer-method-bank"]');
        const payCode = wrapper.get('[data-testid="payer-method-pay-code"]');

        expect(bank.attributes('disabled')).toBeDefined();
        expect(payCode.attributes('disabled')).toBeDefined();
        expect(bank.text()).toContain('Not yet available');
        expect(payCode.text()).toContain('Not yet available');

        await bank.trigger('click');
        await payCode.trigger('click');

        expect(routerPost).not.toHaveBeenCalled();
    });

    it('renders a neutral X-Ray state when no rider message exists', () => {
        const wrapper = mount(PaymentPage, {
            props: {
                payment: {
                    ...pendingPayment,
                    rider_message: null,
                },
            },
        });

        expect(wrapper.get('[data-testid="payer-xray-step"]').text()).toContain(
            'The issuer did not include a payment message.',
        );
    });

    it('prints the settled receipt and hides payment-method actions', async () => {
        const print = vi.fn();
        Object.defineProperty(window, 'print', {
            configurable: true,
            value: print,
        });

        const wrapper = mount(PaymentPage, {
            props: {
                payment: {
                    ...pendingPayment,
                    collected_amount_minor: 10000,
                    amount_due_minor: 0,
                    is_fully_paid: true,
                    can_create_attempt: false,
                    receipt: {
                        pay_code: 'PAY-1234',
                        amount_paid_minor: 10000,
                        currency: 'PHP',
                        completed_at: '2026-08-26T08:30:00+08:00',
                        payments: [
                            {
                                collection_number: 1,
                                amount_paid_minor: 10000,
                                provider: 'netbank',
                                receipt_reference: 'PAY-PAY-1234-01',
                                completed_at: '2026-08-26T08:30:00+08:00',
                            },
                        ],
                    },
                },
            },
        });

        const receipt = wrapper.get('[data-testid="payer-receipt"]');

        expect(receipt.text()).toContain('Payment complete');
        expect(receipt.text()).toContain('₱100.00');
        expect(receipt.text()).toContain('netbank');
        expect(receipt.text()).toContain('PAY-PAY-1234-01');
        expect(
            wrapper.find('[data-testid="payer-funding-methods-step"]').exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="payer-print-receipt"]')
            .trigger('click');

        expect(print).toHaveBeenCalledOnce();
        expect(routerPost).not.toHaveBeenCalled();
    });
});
