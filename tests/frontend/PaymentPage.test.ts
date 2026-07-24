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
    provider: 'netbank',
    provider_available: true,
    can_create_attempt: true,
    attempt: null,
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

        await wrapper.get('button').trigger('click');

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
        expect(wrapper.text()).toContain('Check NetBank');
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
            .findAll('button')
            .find((button) => button.text().includes('Check NetBank'))
            ?.trigger('click');

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

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain(
            'NetBank payment is not available in this environment.',
        );
    });
});
